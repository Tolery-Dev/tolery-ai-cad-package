<?php

namespace Tolery\AiCad\Livewire\Admin;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Livewire\Component;
use Tolery\AiCad\Models\Chat;
use Tolery\AiCad\Services\ZipGeneratorService;

class ChatDetail extends Component
{
    public Chat $chat;

    public function mount(Chat $chat): void
    {
        $this->chat = $chat->load(['messages.user', 'team']);
    }

    public function downloadZip(): void
    {
        Log::info('[ADMIN] Generating ZIP for chat detail', ['chat_id' => $this->chat->id]);

        $zipService = app(ZipGeneratorService::class);
        $result = $zipService->generateChatFilesZip($this->chat);

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

        Log::info('[ADMIN] Triggering download from chat detail', [
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

    public function render(): View
    {
        return view('ai-cad::livewire.admin.chat-detail');
    }
}
