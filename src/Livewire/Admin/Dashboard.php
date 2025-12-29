<?php

namespace Tolery\AiCad\Livewire\Admin;

use Illuminate\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;
use Tolery\AiCad\Models\Chat;
use Tolery\AiCad\Models\ChatDownload;
use Tolery\AiCad\Models\FilePurchase;

class Dashboard extends Component
{
    #[Url]
    public string $period = 'month';

    /**
     * @return array{total_revenue: float, purchase_count: int, conversation_count: int, download_count: int}
     */
    public function getKpis(): array
    {
        $startDate = match ($this->period) {
            'day' => now()->startOfDay(),
            'week' => now()->startOfWeek(),
            'month' => now()->startOfMonth(),
            'year' => now()->startOfYear(),
            default => now()->startOfMonth(),
        };

        $totalAmount = FilePurchase::where('purchased_at', '>=', $startDate)->sum('amount');

        return [
            'total_revenue' => $totalAmount / 100,
            'purchase_count' => FilePurchase::where('purchased_at', '>=', $startDate)->count(),
            'conversation_count' => Chat::where('created_at', '>=', $startDate)->count(),
            'download_count' => ChatDownload::where('downloaded_at', '>=', $startDate)->count(),
        ];
    }

    public function render(): View
    {
        return view('ai-cad::livewire.admin.dashboard', [
            'kpis' => $this->getKpis(),
        ]);
    }
}
