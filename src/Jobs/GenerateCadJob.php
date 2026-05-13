<?php

namespace Tolery\AiCad\Jobs;

use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Tolery\AiCad\Enum\GenerationStatus;
use Tolery\AiCad\Events\CadGenerationCompleted;
use Tolery\AiCad\Events\CadGenerationFailed;
use Tolery\AiCad\Events\CadGenerationProgress;
use Tolery\AiCad\Events\CadGenerationStarted;
use Tolery\AiCad\Models\ChatMessage;
use Tolery\AiCad\Notifications\CadGenerationCompletedNotification;
use Tolery\AiCad\Notifications\CadGenerationFailedNotification;
use Tolery\AiCad\Services\AICADClient;

/**
 * Streams a CAD generation from the DFM API in the background so the browser
 * is no longer a single point of failure (closing the tab, reload, network blip
 * etc. used to lose the piece even though DFM had generated it).
 *
 * Phase 2 of issue #152.
 */
class GenerateCadJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 900;  // 15 minutes — matches the worker `tolerycad-long` timeout

    public function __construct(
        public int $messageId,
        public string $userMessage,
        public ?string $sessionId,
        public bool $isEditRequest,
        public string $materialChoice,
    ) {
        $this->onQueue('tolerycad-long');
    }

    /**
     * Used by ShouldBeUnique to prevent a second generation on the same chat
     * while one is already in flight.
     */
    public function uniqueId(): string
    {
        return 'cad-gen-chat-'.($this->getMessage()?->chat_id ?? $this->messageId);
    }

    public function uniqueFor(): int
    {
        return 900;
    }

    public function handle(AICADClient $client): void
    {
        $message = $this->getMessage();

        if (! $message) {
            Log::warning('[AICAD] GenerateCadJob: message not found', ['id' => $this->messageId]);

            return;
        }

        $message->update([
            'generation_status' => GenerationStatus::RUNNING,
            'generation_started_at' => now(),
            'generation_progress_pct' => 0,
            'generation_progress_step' => 'analysis',
            'generation_progress_message' => 'Démarrage…',
            'generation_error' => null,
        ]);

        CadGenerationStarted::dispatch($message);

        // Throttle progress DB writes — many events per second otherwise.
        $lastDbWriteAt = 0;

        try {
            $client->streamToCallback(
                message: $this->userMessage,
                projectId: $this->sessionId,
                isEditRequest: $this->isEditRequest,
                timeoutSec: 600,
                materialChoice: $this->materialChoice,
                onProgress: function (array $event) use ($message, &$lastDbWriteAt) {
                    $step = $event['step'] ?? null;
                    $pct = isset($event['overall_percentage']) ? (int) $event['overall_percentage'] : null;
                    $msg = $event['message'] ?? $event['status'] ?? null;

                    // Always broadcast (cheap) — DB writes throttled to 1/s.
                    CadGenerationProgress::dispatch($message, $step, $pct, $msg);

                    $now = microtime(true);
                    if ($now - $lastDbWriteAt < 1.0) {
                        return;
                    }
                    $lastDbWriteAt = $now;

                    $message->update(array_filter([
                        'generation_progress_step' => $step,
                        'generation_progress_pct' => $pct,
                        'generation_progress_message' => $msg,
                    ], static fn ($v) => $v !== null));
                },
                onComplete: function (array $finalResponse) use ($message) {
                    $message->update([
                        'message' => $finalResponse['chat_response'] ?? $message->message,
                        'ai_cad_path' => $finalResponse['obj_export'] ?? null,
                        'ai_step_path' => $finalResponse['step_export'] ?? null,
                        'ai_json_edge_path' => $finalResponse['attribute_and_transientid_map'] ?? null,
                        'ai_technical_drawing_path' => $finalResponse['technical_drawing'] ?? null,
                        'ai_screenshot_path' => $finalResponse['screenshot'] ?? null,
                        'cad_files_ready' => false,  // DownloadCadAssetsJob will flip this to true
                        'generation_status' => GenerationStatus::COMPLETED,
                        'generation_completed_at' => now(),
                        'generation_progress_pct' => 100,
                        'generation_progress_step' => 'complete',
                        'generation_progress_message' => 'Terminé',
                    ]);

                    CadGenerationCompleted::dispatch($message);

                    $user = $message->chat?->user ?? $message->user;
                    if ($user) {
                        $user->notify(new CadGenerationCompletedNotification($message));
                    }
                },
                onError: function (int $curlErrno, int $httpCode, string $error) use ($message) {
                    $message->update([
                        'generation_status' => GenerationStatus::FAILED,
                        'generation_completed_at' => now(),
                        'generation_error' => "errno={$curlErrno} http={$httpCode} {$error}",
                    ]);

                    CadGenerationFailed::dispatch($message, $error);

                    $user = $message->chat?->user ?? $message->user;
                    if ($user) {
                        $user->notify(new CadGenerationFailedNotification($message, $error));
                    }
                },
            );
        } catch (\Throwable $e) {
            // onError already updated the DB + dispatched the event + notified the user.
            // We re-report here so Nightwatch keeps the full stack trace.
            report($e);
        }
    }

    public function failed(\Throwable $e): void
    {
        // Safety net: if the job dies before/around the onError callback
        // (timeout, worker SIGKILL, etc.), still mark the message as failed.
        $message = $this->getMessage();
        if (! $message || $message->generation_status === GenerationStatus::COMPLETED) {
            return;
        }

        $message->update([
            'generation_status' => GenerationStatus::FAILED,
            'generation_completed_at' => $message->generation_completed_at ?? now(),
            'generation_error' => $message->generation_error ?? ('Job failed: '.$e->getMessage()),
        ]);

        CadGenerationFailed::dispatch($message, $e->getMessage());

        $user = $message->chat?->user ?? $message->user;
        if ($user) {
            $user->notify(new CadGenerationFailedNotification($message, $e->getMessage()));
        }
    }

    protected function getMessage(): ?ChatMessage
    {
        return ChatMessage::find($this->messageId);
    }
}
