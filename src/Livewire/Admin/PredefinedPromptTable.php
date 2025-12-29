<?php

namespace Tolery\AiCad\Livewire\Admin;

use Illuminate\Database\Eloquent\Builder;
use Tolery\AiCad\Models\PredefinedPrompt;
use Ultraviolettes\FluxDataTable\Livewire\FluxDataTable;

class PredefinedPromptTable extends FluxDataTable
{
    public function builder(): Builder
    {
        return PredefinedPrompt::query()
            ->orderBy('sort_order')
            ->orderBy('name');
    }

    public function columns(): array
    {
        return [
            [
                'label' => 'Ordre',
                'field' => 'sort_order',
                'sortable' => true,
            ],
            [
                'label' => 'Nom',
                'field' => 'name',
                'searchable' => true,
                'sortable' => true,
            ],
            [
                'label' => 'Matériau',
                'field' => 'material_family',
                'render' => fn ($row) => $row->material_family?->label() ?? 'Tous',
            ],
            [
                'label' => 'Statut',
                'field' => 'active',
                'render' => fn ($row) => $row->active
                    ? '<span class="text-green-600">Actif</span>'
                    : '<span class="text-gray-400">Inactif</span>',
            ],
            [
                'label' => 'Actions',
                'field' => 'id',
                'render' => fn ($row) => view('ai-cad::livewire.admin.partials.prompt-actions', ['prompt' => $row])->render(),
            ],
        ];
    }
}
