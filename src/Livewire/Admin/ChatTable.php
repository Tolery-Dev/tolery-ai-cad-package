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
            ->with('team')
            ->withTrashed()
            ->latest();
    }

    public function columns(): array
    {
        return [
            [
                'label' => 'Nom',
                'field' => 'name',
                'searchable' => true,
                'sortable' => true,
                'render' => fn ($row) => $row->name ?: 'Sans nom',
            ],
            [
                'label' => 'Équipe',
                'field' => 'team_id',
                'searchable' => true,
                'render' => fn ($row) => $row->team->name ?? '-',
            ],
            [
                'label' => 'Session ID',
                'field' => 'session_id',
                'searchable' => true,
                'sortable' => true,
                'render' => fn ($row) => $row->session_id
                    ? '<div x-data="{ copied: false }" class="flex items-center gap-1.5">
                        <code class="text-xs bg-gray-100 px-1.5 py-0.5 rounded select-all">'.e($row->session_id).'</code>
                        <button type="button" @click="navigator.clipboard.writeText(\''.e($row->session_id).'\'); copied = true; setTimeout(() => copied = false, 2000)"
                            class="p-1 rounded hover:bg-gray-100 text-gray-500 hover:text-gray-700" title="Copier">
                            <svg x-show="!copied" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                            </svg>
                            <svg x-show="copied" x-cloak class="w-4 h-4 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                            </svg>
                        </button>
                    </div>'
                    : '<span class="text-gray-400">-</span>',
            ],
            [
                'label' => 'Matériau',
                'field' => 'material_family',
                'render' => fn ($row) => $row->material_family?->label() ?? '-',
            ],
            [
                'label' => 'Messages',
                'field' => 'messages_count',
                'sortable' => true,
            ],
            [
                'label' => 'Pièce générée',
                'field' => 'has_generated_piece',
                'render' => fn ($row) => $row->has_generated_piece
                    ? '<span class="text-green-600">Oui</span>'
                    : '<span class="text-gray-400">Non</span>',
            ],
            [
                'label' => 'Date',
                'field' => 'created_at',
                'sortable' => true,
                'render' => fn ($row) => $row->created_at->format('d/m/Y H:i'),
            ],
            [
                'label' => 'Statut',
                'field' => 'deleted_at',
                'render' => fn ($row) => $row->trashed()
                    ? '<span class="text-red-600">Supprimé</span>'
                    : '<span class="text-green-600">Actif</span>',
            ],
            [
                'label' => 'Actions',
                'field' => 'id',
                'render' => fn ($row) => view('ai-cad::livewire.admin.partials.chat-actions', ['chat' => $row])->render(),
            ],
        ];
    }
}
