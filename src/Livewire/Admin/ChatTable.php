<?php

namespace Tolery\AiCad\Livewire\Admin;

use Illuminate\Database\Eloquent\Builder;
use Tolery\AiCad\Models\Chat;
use Ultraviolettes\FluxDataTable\Livewire\FluxDataTable;

class ChatTable extends FluxDataTable
{
    public function builder(): Builder
    {
        return Chat::query()
            ->withCount('messages')
            ->with([
                'team.subscriptions.items',
                'user',
                'downloads',
                'filePurchases',
                'latestAssistantMessage',
                'messages' => fn ($q) => $q->whereNotNull('ai_screenshot_path')->latest()->limit(1),
            ])
            ->withTrashed()
            ->latest();
    }

    public function columns(): array
    {
        return [
            [
                'label' => 'Conversation',
                'field' => 'name',
                'searchable' => true,
                'sortable' => true,
                'align' => 'left',
                'render' => fn ($row) => view('ai-cad::livewire.admin.partials.chat-name-cell', ['chat' => $row])->render(),
            ],
            [
                'label' => 'Équipe',
                'field' => 'team_id',
                'searchable' => true,
                'render' => fn ($row) => '<span class="text-zinc-700 dark:text-zinc-300">'.e($row->team->name ?? '-').'</span>',
            ],
            [
                'label' => 'Abonnement',
                'field' => 'team_id',
                'render' => function ($row) {
                    if (! $row->team) {
                        return '<span class="text-zinc-400">-</span>';
                    }

                    $product = $row->team->getSubscriptionProduct();

                    if ($product) {
                        return '<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-violet-50 text-violet-700 dark:bg-violet-900/30 dark:text-violet-400">'
                            .e($product->name).'</span>';
                    }

                    return '<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-zinc-100 text-zinc-500 dark:bg-zinc-800 dark:text-zinc-400">Aucun</span>';
                },
            ],
            [
                'label' => 'Messages',
                'field' => 'messages_count',
                'sortable' => true,
                'render' => fn ($row) => '<span class="inline-flex items-center justify-center min-w-6 px-2 py-0.5 rounded-full text-xs font-medium bg-zinc-100 text-zinc-600 dark:bg-zinc-800 dark:text-zinc-400">'
                    .$row->messages_count.'</span>',
            ],
            [
                'label' => 'Statut',
                'field' => 'has_generated_piece',
                'render' => function ($row) {
                    $badges = '';

                    // Générée
                    if ($row->has_generated_piece) {
                        $badges .= '<span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-xs font-medium bg-green-50 text-green-700 dark:bg-green-900/30 dark:text-green-400">'
                            .'<svg class="size-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>'
                            .'Générée</span>';
                    }

                    // Téléchargé
                    if ($row->downloads->isNotEmpty()) {
                        $badges .= ' <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-xs font-medium bg-blue-50 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400">'
                            .'<svg class="size-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>'
                            .'Téléchargé</span>';
                    }

                    // Achat sans abonnement (achat CB one-shot)
                    if ($row->filePurchases->isNotEmpty()) {
                        $hasSubscription = $row->team?->getSubscriptionProduct() !== null;

                        if (! $hasSubscription) {
                            $badges .= ' <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-xs font-medium bg-amber-50 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400">'
                                .'<svg class="size-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg>'
                                .'Achat sans abonnement</span>';
                        }
                    }

                    // Supprimée
                    if ($row->trashed()) {
                        $badges .= ' <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-red-50 text-red-700 dark:bg-red-900/30 dark:text-red-400">Supprimée</span>';
                    }

                    // Bug — stream jamais terminé (TYPING_INDICATOR) ou erreur DFM dans la conversation
                    $isBug = $row->latestAssistantMessage?->message === '[TYPING_INDICATOR]'
                        || $row->has_dfm_error;

                    if ($isBug) {
                        $badges .= ' <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-xs font-medium bg-orange-50 text-orange-700 dark:bg-orange-900/30 dark:text-orange-400">'
                            .'<svg class="size-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4.5c-.77-.833-2.694-.833-3.464 0L3.34 16.5c-.77.833.192 2.5 1.732 2.5z"/></svg>'
                            .'Bug</span>';
                    }

                    return $badges ?: '<span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-xs font-medium bg-zinc-100 text-zinc-500 dark:bg-zinc-800 dark:text-zinc-400">'
                        .'<svg class="size-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>'
                        .'En cours</span>';
                },
            ],
            [
                'label' => 'Date',
                'field' => 'created_at',
                'sortable' => true,
                'render' => fn ($row) => '<div class="text-sm">'
                    .'<div class="text-zinc-700 dark:text-zinc-300">'.$row->created_at->format('d/m/Y').'</div>'
                    .'<div class="text-xs text-zinc-400">'.$row->created_at->format('H:i').'</div>'
                    .'</div>',
            ],
            [
                'label' => '',
                'field' => 'id',
                'render' => fn ($row) => view('ai-cad::livewire.admin.partials.chat-actions', ['chat' => $row])->render(),
            ],
        ];
    }
}
