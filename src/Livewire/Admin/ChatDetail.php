<?php

namespace Tolery\AiCad\Livewire\Admin;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\View\View;
use Livewire\Component;
use Tolery\AiCad\Models\Chat;
use Tolery\AiCad\Models\ChatMessage;

class ChatDetail extends Component
{
    public Chat $chat;

    public function mount(Chat $chat): void
    {
        $this->chat = $chat->load([
            'team',
            'user',
            'messages' => fn ($query) => $query
                ->with('user')
                ->orderBy('created_at')
                ->orderBy('id'),
        ]);
    }

    /**
     * Get all assistant message versions that have 3D model data.
     *
     * @return array<int, array{label: string, jsonUrl: string, screenshotUrl: string|null}>
     */
    public function getViewerVersions(): array
    {
        return $this->chat->messages
            ->filter(fn (ChatMessage $m) => $m->role === 'assistant' && $m->ai_json_edge_path)
            ->values()
            ->map(fn (ChatMessage $m, int $i) => [
                'label' => 'v'.($i + 1),
                'jsonUrl' => $m->getJSONEdgeUrl(),
                'screenshotUrl' => $m->getScreenshotUrl(),
            ])
            ->all();
    }

    public function downloadZip(): void
    {
        Log::info('[ADMIN] Generating signed download URL for chat', ['chat_id' => $this->chat->id]);

        // Vérifier l'autorisation
        $this->authorize('downloadFiles', $this->chat);

        // Déterminer si S3 est disponible
        $disk = Storage::disk(config('ai-cad.storage_disk', 's3'));
        $useS3 = method_exists($disk->getAdapter(), 'temporaryUrl');

        // Générer l'URL signée appropriée
        if ($useS3) {
            $downloadUrl = URL::temporarySignedRoute(
                'ai-cad.admin.download.s3',
                now()->addMinutes(5),
                ['chat' => $this->chat->id]
            );
        } else {
            $downloadUrl = URL::temporarySignedRoute(
                'ai-cad.admin.download',
                now()->addMinutes(5),
                ['chat' => $this->chat->id]
            );
        }

        Log::info('[ADMIN] Signed download URL generated', [
            'chat_id' => $this->chat->id,
            'uses_s3' => $useS3,
        ]);

        // Ouvrir l'URL dans un nouvel onglet
        $this->js("window.open('{$downloadUrl}', '_blank');");
    }

    public function render(): View
    {
        return view('ai-cad::livewire.admin.chat-detail');
    }
}
