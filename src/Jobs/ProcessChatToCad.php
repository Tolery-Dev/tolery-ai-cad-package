<?php

namespace Tolery\AiCad\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Tolery\AiCad\Models\ChatMessage;
use Tolery\AiCad\Services\AICADClient;

class ProcessChatToCad implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** Timeout Laravel du job (worker: --timeout=600) */
    public int $timeout = 600;
    public int $tries = 3;

    public function __construct(
        public int $chatId,
        public int $assistantMessageId,
        public string $message,
        public int $timeoutSec = 180,
    ) {
    }

    public function handle(AICADClient $api): void
    {
        /** @var ChatMessage|null $asst */
        $asst = ChatMessage::query()->find($this->assistantMessageId);
        if (! $asst) {
            return;
        }

        try {
            foreach ($api->generateCadStream(
                message: $this->message,
                projectId: (string) $this->chatId,
                timeoutSec: $this->timeoutSec
            ) as $event) {
                $type = $event['type'] ?? null;

                if ($type === 'progress') {
                    $step = $event['step'] ?? '';
                    $status = $event['status'] ?? ($event['message'] ?? '');
                    $pct = (int) ($event['overall_percentage'] ?? 0);
                    $asst->message = trim(sprintf("AI thinking… [%s] %s (%d%%)", $step, $status, $pct));
                    $asst->save();
                    continue;
                }

                if ($type === 'final') {
                    $final = (array) ($event['final_response'] ?? []);
                    $asst->message = (string) ($final['chat_response'] ?? 'OK');

                    // URLs d’export (OBJ/STEP/Tessellated)
                    if (! empty($final['obj_export'])) {
                        $asst->ai_cad_path = (string) $final['obj_export'];
                    }
                    if (! empty($final['tessellated_export'])) {
                        $asst->ai_json_edge_path = (string) $final['tessellated_export'];
                    }
                    // Autres champs possibles: step_export, attribute_and_transientid_map, manufacturing_errors

                    $asst->save();
                }
            }
        } catch (ConnectionException $e) {
            // En cas de timeout réseau: message d’attente et retry doux
            $asst->message = 'Toujours en calcul côté serveur…';
            $asst->save();
            $this->release(min(60, 5 * ($this->attempts() ?: 1)));
        }
    }
}
