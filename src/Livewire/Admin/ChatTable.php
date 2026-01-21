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
            ->with(['team', 'user', 'messages' => fn ($q) => $q->whereNotNull('ai_screenshot_path')->latest()->limit(1)])
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
                'render' => fn ($row) => view('ai-cad::livewire.admin.partials.chat-name-cell', ['chat' => $row])->render(),
            ],
            [
                'label' => 'Équipe',
                'field' => 'team_id',
                'searchable' => true,
                'render' => fn ($row) => '<span class="text-zinc-700 dark:text-zinc-300">'.e($row->team->name ?? '-').'</span>',
            ],
            [
                'label' => 'Matériau',
                'field' => 'material_family',
                'render' => fn ($row) => $row->material_family
                    ? '<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-zinc-100 text-zinc-700 dark:bg-zinc-800 dark:text-zinc-300">'
                        .e($row->material_family->label()).'</span>'
                    : '<span class="text-zinc-400">-</span>',
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
                    if ($row->has_generated_piece) {
                        $badges .= '<span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-xs font-medium bg-green-50 text-green-700 dark:bg-green-900/30 dark:text-green-400">'
                            .'<svg class="size-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>'
                            .'Générée</span>';
                    }
                    if ($row->trashed()) {
                        $badges .= ' <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-red-50 text-red-700 dark:bg-red-900/30 dark:text-red-400">Supprimée</span>';
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
