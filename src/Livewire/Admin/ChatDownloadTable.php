<?php

namespace Tolery\AiCad\Livewire\Admin;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Tolery\AiCad\Models\ChatDownload;
use Tolery\AiCad\Services\ZipGeneratorService;
use Ultraviolettes\FluxDataTable\Livewire\FluxDataTable;

class ChatDownloadTable extends FluxDataTable
{
    public ?string $sortBy = 'downloaded_at';

    public string $sortDirection = 'desc';

    public function builder(): Builder
    {
        return ChatDownload::query()
            ->with(['team', 'chat', 'message']);
    }

    public function columns(): array
    {
        return [
            [
                'label' => 'Date',
                'field' => 'downloaded_at',
                'sortable' => true,
                'render' => fn ($row) => $row->downloaded_at?->format('d/m/Y H:i') ?? '-',
            ],
            [
                'label' => 'Équipe',
                'field' => 'team_id',
                'render' => fn ($row) => $row->team->name ?? '-',
            ],
            [
                'label' => 'Conversation',
                'field' => 'chat_id',
                'render' => fn ($row) => $row->chat
                    ? '<a href="'.route('ai-cad.admin.chats.show', $row->chat).'" class="text-blue-600 hover:underline">'
                        .e($row->chat->name ?: 'Chat #'.$row->chat_id)
                        .'</a>'
                    : '-',
            ],
            [
                'label' => 'Type fichier',
                'field' => 'file_type',
                'render' => fn ($row) => $row->file_type
                    ? '<span class="font-mono text-xs uppercase">'.e($row->file_type).'</span>'
                    : '-',
            ],
            [
                'label' => 'Message',
                'field' => 'message_id',
                'render' => fn ($row) => $row->message_id ? '#'.$row->message_id : '-',
            ],
            [
                'label' => 'Actions',
                'field' => 'actions',
                'render' => fn ($row) => $row->chat
                    ? '<button wire:click="downloadZip('.$row->chat_id.')" class="text-sm text-violet-600 hover:text-violet-800 dark:text-violet-400 dark:hover:text-violet-300">
                        <svg class="inline-block w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                        </svg>
                        Télécharger ZIP
                    </button>'
                    : '-',
            ],
        ];
    }

    public function downloadZip(int $chatId): void
    {
        $chat = \Tolery\AiCad\Models\Chat::find($chatId);

        if (! $chat) {
            $this->js("Flux.toast({ heading: 'Erreur', text: 'Conversation introuvable', variant: 'danger' })");
            return;
        }

        Log::info('[ADMIN] Generating ZIP for download', ['chat_id' => $chatId]);

        $zipService = app(ZipGeneratorService::class);
        $result = $zipService->generateChatFilesZip($chat);

        if (! $result['success']) {
            Log::error('[ADMIN] ZIP generation failed', ['error' => $result['error']]);
            $this->js("Flux.toast({ heading: 'Erreur', text: '{$result['error']}', variant: 'danger' })");
            return;
        }

        // Stocker le ZIP dans un emplacement accessible
        $publicPath = 'downloads/'.basename($result['path']);
        Storage::disk('public')->put($publicPath, file_get_contents($result['path']));

        // Supprimer le fichier temporaire
        @unlink($result['path']);

        // Déclencher le téléchargement via JavaScript
        $downloadUrl = Storage::disk('public')->url($publicPath);
        $filename = $result['filename'];

        Log::info('[ADMIN] Triggering download', [
            'url' => $downloadUrl,
            'filename' => $filename,
        ]);

        $this->js("
            (function() {
                const link = document.createElement('a');
                link.href = '{$downloadUrl}';
                link.download = '{$filename}';
                link.style.display = 'none';
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
            })();
            Flux.toast({ heading: 'Téléchargement lancé', text: 'Le fichier ZIP est en cours de téléchargement.', variant: 'success' });
        ");
    }
}
