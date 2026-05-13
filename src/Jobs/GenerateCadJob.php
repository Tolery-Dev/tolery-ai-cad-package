<?php

namespace Tolery\AiCad\Jobs;

use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
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
                    $this->finalizeMessage($message, $finalResponse);

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

    /**
     * Reproduces the persistence logic that used to live in `Chatbot::saveStreamFinal()`
     * so the new async flow ends in the same DB state as the synchronous one:
     *
     *  - resolve all file URLs from the final_response (supports legacy `_path` keys)
     *  - persist `chat.session_id` if it changed
     *  - download the JSON model locally (so the Three.js viewer hits our domain — no CORS)
     *  - flip chat-level flags (`has_generated_piece`, `has_dfm_error`)
     *  - dispatch `DownloadCadAssetsJob` for OBJ / STEP / PDF if any URLs are present;
     *    otherwise mark `cad_files_ready = true` directly so the UI doesn't spin forever.
     *
     * @param  array<string, mixed>  $final
     */
    protected function finalizeMessage(ChatMessage $message, array $final): void
    {
        $chatResponse = (string) ($final['chat_response'] ?? '');

        // Dual-key URL extraction (legacy `_path` for backward compat)
        $objUrl = $final['obj_export'] ?? $final['obj_path'] ?? null;
        $stepUrl = $final['step_export'] ?? $final['step_path'] ?? null;
        $jsonModelUrl = $final['json_export'] ?? $final['json_path'] ?? null;
        $tessUrl = $final['tessellated_export'] ?? $final['tessellated_path'] ?? null;
        $techDrawingUrl = $final['technical_drawing_export'] ?? $final['technical_drawing_path'] ?? null;
        $screenshotUrl = $final['screenshot'] ?? null;

        $resolvedJsonUrl = ($jsonModelUrl !== null && $jsonModelUrl !== '') ? $jsonModelUrl
            : (($tessUrl !== null && $tessUrl !== '') ? $tessUrl : null);

        $chat = $message->chat;

        // Persist session_id if Stripe-like upstream changed it
        if ($chat && isset($final['session_id']) && $chat->session_id !== $final['session_id']) {
            $chat->session_id = $final['session_id'];
            $chat->save();
        }

        // Download JSON synchronously into Storage so the viewer reaches it via the
        // same-origin proxy route (no CORS). On failure we fall back to the external URL.
        $localJsonPath = null;
        if ($resolvedJsonUrl !== null && $chat !== null) {
            $localJsonPath = $this->downloadJsonModel($resolvedJsonUrl, $chat->getStorageFolder()) ?? $resolvedJsonUrl;
        }

        $messageText = $chatResponse !== '' ? $chatResponse : ($message->message ?: 'Fichier généré avec succès.');

        $message->update([
            'message' => $messageText,
            'ai_cad_path' => $objUrl,
            'ai_step_path' => $stepUrl,
            'ai_json_edge_path' => $localJsonPath,
            'ai_technical_drawing_path' => $techDrawingUrl,
            'ai_screenshot_path' => $screenshotUrl,
            'cad_files_ready' => false, // flipped to true below (no assets) or by DownloadCadAssetsJob
            'generation_status' => GenerationStatus::COMPLETED,
            'generation_completed_at' => now(),
            'generation_progress_pct' => 100,
            'generation_progress_step' => 'complete',
            'generation_progress_message' => 'Terminé',
        ]);

        // Chat-level flags (first successful generation, DFM error detection)
        if ($chat) {
            $isSuccessfulGeneration = $resolvedJsonUrl || $objUrl || $stepUrl;
            if ($isSuccessfulGeneration && ! $chat->has_generated_piece) {
                $chat->has_generated_piece = true;
                $chat->save();
            }

            if (! $chat->has_dfm_error) {
                $dfmCodes = $this->loadDfmErrorCodes();
                if (isset($dfmCodes[trim($messageText)])) {
                    $chat->has_dfm_error = true;
                    $chat->save();
                }
            }
        }

        // Background download of OBJ / STEP / PDF — same job the sync flow uses.
        $urlsForJob = array_filter([
            'obj' => ($objUrl !== null && $objUrl !== '') ? $objUrl : null,
            'step' => ($stepUrl !== null && $stepUrl !== '') ? $stepUrl : null,
            'pdf' => ($techDrawingUrl !== null && $techDrawingUrl !== '') ? $techDrawingUrl : null,
        ]);

        if (! empty($urlsForJob) && $chat !== null) {
            DownloadCadAssetsJob::dispatch($message->id, $urlsForJob, $chat->getStorageFolder());
        } else {
            $message->cad_files_ready = true;
            $message->save();
        }

        Log::info('[AICAD] GenerateCadJob finalized message', [
            'message_id' => $message->id,
            'chat_id' => $chat?->id,
            'pending_keys' => array_keys($urlsForJob),
            'json_local' => $localJsonPath !== $resolvedJsonUrl,
        ]);
    }

    /**
     * Download a JSON model from the DFM API and store it locally. Returns the
     * local Storage path on success, or null on failure (caller falls back to
     * the external URL, which is then proxied by CadFileController).
     */
    protected function downloadJsonModel(string $url, string $folder): ?string
    {
        try {
            $apiKey = config('ai-cad.api.key');
            $response = Http::when($apiKey, fn ($req) => $req->withToken($apiKey))
                ->timeout(30)
                ->get($url);

            if (! $response->successful()) {
                Log::warning('[AICAD] JSON download failed', ['status' => $response->status(), 'url' => $url]);

                return null;
            }

            $localPath = $folder.'/'.uniqid('cad_json_').'.json';
            Storage::put($localPath, $response->body());

            return $localPath;
        } catch (\Throwable $e) {
            Log::warning('[AICAD] JSON download exception', ['url' => $url, 'error' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * Same DFM error code lookup as `Chatbot::loadDfmErrorCodes()` — duplicated here
     * because the source method is `protected` on the Livewire component. Returns
     * a `{code: message}` map for the current locale (defaults to French).
     *
     * @return array<string, string>
     */
    protected function loadDfmErrorCodes(): array
    {
        $modelClass = config('ai-cad.dfm_error_code_model');

        if (! $modelClass || ! class_exists($modelClass)) {
            return [];
        }

        $messageColumn = app()->getLocale() === 'fr' ? 'message_fr' : 'message_en';

        return $modelClass::query()
            ->pluck($messageColumn, 'code')
            ->filter()
            ->all();
    }
}
