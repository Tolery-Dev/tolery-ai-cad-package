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
                    ? '<code class="text-xs bg-gray-100 px-1.5 py-0.5 rounded">'.e(substr($row->session_id, 0, 12)).'...</code>'
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
