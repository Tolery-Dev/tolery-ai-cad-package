<?php

namespace Tolery\AiCad\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;
use Tolery\AiCad\Models\ChatMessage;

class DownloadCadAssetsJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 120;

    /**
     * Espacement (en secondes) entre les tentatives. Laisse à l'API DFM le temps
     * de se remettre d'un échec transitoire avant de retenter (#2438).
     *
     * @var array<int, int>
     */
    public array $backoff = [10, 30];

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
            $message->cad_files_failed = false;
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

        // All-or-nothing : on bufferise tous les contenus en mémoire et on ne
        // persiste (Storage + champs DB) qu'une fois TOUS les assets récupérés.
        // Si un seul échoue, on lève une exception pour déclencher un retry sans
        // laisser de champ pointer vers une URL externe (qui ferait planter
        // ZipGeneratorService → « Aucun fichier disponible pour ce chat ») ni
        // marquer cad_files_ready = true à tort (#2438).
        $downloaded = [];
        $failed = [];

        foreach ($urlsToDownload as $key => $url) {
            /** @var Response|null $response */
            $response = $responses[$key] ?? null;

            if (! $response || ! $response->successful()) {
                $failed[] = $key;
                Log::warning("[AICAD] DownloadCadAssetsJob: échec téléchargement {$key}", [
                    'url' => $url,
                    'status' => $response?->status(),
                ]);

                continue;
            }

            $ext = $extensionMap[$key] ?? 'bin';
            $filename = uniqid("cad_{$key}_").'.'.$ext;

            $downloaded[$key] = [
                'field' => $fieldMap[$key] ?? null,
                'path' => "{$this->storageFolder}/{$filename}",
                'body' => $response->body(),
            ];
        }

        if (! empty($failed)) {
            throw new RuntimeException(
                '[AICAD] DownloadCadAssetsJob: assets non téléchargés ('.implode(', ', $failed).') pour le message '.$this->messageId
            );
        }

        foreach ($downloaded as $key => $info) {
            Storage::put($info['path'], $info['body']);

            if ($info['field']) {
                $message->{$info['field']} = $info['path'];
            }

            Log::info("[AICAD] DownloadCadAssetsJob: {$key} stocké", ['path' => $info['path']]);
        }

        $message->cad_files_ready = true;
        $message->cad_files_failed = false;
        $message->save();

        Log::info('[AICAD] DownloadCadAssetsJob: terminé', ['message_id' => $this->messageId]);
    }

    /**
     * Échec définitif après épuisement des tentatives : on marque le message pour
     * que l'UI prévienne l'utilisateur (toast honnête) et propose un nouvel essai,
     * plutôt que de laisser la modal de préparation tourner indéfiniment (#2438).
     */
    public function failed(?Throwable $exception): void
    {
        $message = ChatMessage::find($this->messageId);

        if (! $message) {
            return;
        }

        $message->cad_files_ready = false;
        $message->cad_files_failed = true;
        $message->save();

        Log::error('[AICAD] DownloadCadAssetsJob: échec définitif', [
            'message_id' => $this->messageId,
            'error' => $exception?->getMessage(),
        ]);
    }
}
