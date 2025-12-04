<?php

namespace Tolery\AiCad\Livewire;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;
use Tolery\AiCad\Models\Chat;
use Tolery\AiCad\Models\ChatDownload;
use Tolery\AiCad\Models\ChatUser;
use Tolery\AiCad\Models\FilePurchase;
use Tolery\AiCad\Services\ZipGeneratorService;

class ChatHistoryPanel extends Component
{
    public bool $showPanel = false;

    public function openPanel(): void
    {
        $this->showPanel = true;
    }

    public function closePanel(): void
    {
        $this->showPanel = false;
    }

    /**
     * Get download history for the current user's team.
     */
    public function getHistoryProperty(): Collection
    {
        /** @var ChatUser $user */
        $user = auth()->user();
        $team = $user->team;

        // Get downloads via subscription
        $downloads = ChatDownload::with(['chat.messages' => fn ($q) => $q->whereNotNull('ai_step_path')->latest()->limit(1)])
            ->where('team_id', $team->id)
            ->orderByDesc('downloaded_at')
            ->get();

        // Get one-time purchases
        $purchases = FilePurchase::with(['chat.messages' => fn ($q) => $q->whereNotNull('ai_step_path')->latest()->limit(1)])
            ->where('team_id', $team->id)
            ->orderByDesc('purchased_at')
            ->get();

        // Map downloads
        $downloadItems = $downloads->map(fn ($d) => [
            'chat' => $d->chat,
            'date' => $d->downloaded_at,
            'type' => 'subscription',
            'amount' => null,
        ]);

        // Map purchases
        $purchaseItems = $purchases->map(fn ($p) => [
            'chat' => $p->chat,
            'date' => $p->purchased_at,
            'type' => 'purchase',
            'amount' => $p->amount,
        ]);

        // Merge and format
        $history = $downloadItems->concat($purchaseItems);

        $filtered = $history->filter(fn ($item) => $item['chat'] !== null);

        // @phpstan-ignore-next-line
        $unique = $filtered->unique(fn ($item) => $item['chat']->id);

        return $unique->sortByDesc('date')->values()->take(20);
    }

    /**
     * Download a file from the history.
     */
    public function downloadFile(int $chatId): void
    {
        /** @var ChatUser $user */
        $user = auth()->user();
        $team = $user->team;

        $chat = Chat::find($chatId);

        if (! $chat) {
            Log::error('[ChatHistoryPanel] Chat not found', ['chat_id' => $chatId]);

            return;
        }

        // Check if team has access
        $hasDownloaded = ChatDownload::where('team_id', $team->id)
            ->where('chat_id', $chatId)
            ->exists();

        $hasPurchased = FilePurchase::where('team_id', $team->id)
            ->where('chat_id', $chatId)
            ->exists();

        if (! $hasDownloaded && ! $hasPurchased) {
            Log::warning('[ChatHistoryPanel] Unauthorized download attempt', [
                'team_id' => $team->id,
                'chat_id' => $chatId,
            ]);

            return;
        }

        // Generate ZIP
        $zipService = app(ZipGeneratorService::class);
        $result = $zipService->generateChatFilesZip($chat);

        if (! $result['success']) {
            Log::error('[ChatHistoryPanel] ZIP generation failed', ['error' => $result['error']]);
            $this->js("Flux.toast({ heading: 'Erreur', text: '{$result['error']}', variant: 'danger' })");

            return;
        }

        // Store ZIP temporarily and trigger download
        $publicPath = 'downloads/'.basename($result['path']);
        Storage::disk('public')->put($publicPath, file_get_contents($result['path']));

        // Clean up temp file
        @unlink($result['path']);

        $downloadUrl = Storage::disk('public')->url($publicPath);
        $filename = $result['filename'];

        Log::info('[ChatHistoryPanel] Triggering download', [
            'chat_id' => $chatId,
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
            Flux.toast({ heading: 'Telechargement lance', text: 'Votre archive est en cours de telechargement.', variant: 'success' });
        ");
    }

    public function render()
    {
        return view('ai-cad::livewire.chat-history-panel');
    }
}
