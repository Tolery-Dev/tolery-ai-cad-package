<?php

namespace Tolery\AiCad\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Tolery\AiCad\Models\ChatMessage;

class DownloadCadAssetsJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 120;

    /**
     * @param  int  $messageId  ID du ChatMessage à mettre à jour
     * @param  array<string, string>  $urls  ['obj' => url, 'step' => url, 'json' => url, 'pdf' => url]
     * @param  string  $storageFolder  Dossier de stockage du chat (ex: storage/ai-chat/2026-03/chat-42)
     */
    public function __construct(
        public int $messageId,
        public array $urls,
        public string $storageFolder,
    ) {
        $this->onQueue('tolerycad');
    }

    public function handle(): void
    {
        $message = ChatMessage::find($this->messageId);

        if (! $message) {
            Log::warning('[AICAD] DownloadCadAssetsJob: message introuvable', ['id' => $this->messageId]);

            return;
        }

        $urlsToDownload = array_filter($this->urls, fn ($url) => $url !== '');

        if (empty($urlsToDownload)) {
            $message->cad_files_ready = true;
            $message->save();

            return;
        }

        $apiKey = config('ai-cad.api.key');
        $headers = $apiKey ? ['Authorization' => "Bearer {$apiKey}"] : [];

        // Téléchargement concurrent via Http::pool()
        $responses = Http::pool(function (Pool $pool) use ($urlsToDownload, $headers) {
            return collect($urlsToDownload)->map(
                fn ($url, $key) => $pool->as($key)->withHeaders($headers)->timeout(60)->get($url)
            )->all();
        });

        $extensionMap = ['obj' => 'obj', 'step' => 'step', 'json' => 'json', 'pdf' => 'pdf'];
        $fieldMap = [
            'obj' => 'ai_cad_path',
            'step' => 'ai_step_path',
            'json' => 'ai_json_edge_path',
            'pdf' => 'ai_technical_drawing_path',
        ];

        foreach ($urlsToDownload as $key => $url) {
            /** @var Response|null $response */
            $response = $responses[$key] ?? null;

            if (! $response || ! $response->successful()) {
                Log::warning("[AICAD] DownloadCadAssetsJob: échec téléchargement {$key}", [
                    'url' => $url,
                    'status' => $response?->status(),
                ]);

                continue;
            }

            $ext = $extensionMap[$key] ?? 'bin';
            $filename = uniqid("cad_{$key}_").'.'.$ext;
            $path = "{$this->storageFolder}/{$filename}";

            Storage::put($path, $response->body());

            $field = $fieldMap[$key] ?? null;
            if ($field) {
                $message->{$field} = $path;
            }

            Log::info("[AICAD] DownloadCadAssetsJob: {$key} stocké", ['path' => $path]);
        }

        $message->cad_files_ready = true;
        $message->save();

        Log::info('[AICAD] DownloadCadAssetsJob: terminé', ['message_id' => $this->messageId]);
    }
}
