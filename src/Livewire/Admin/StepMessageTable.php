<?php

namespace Tolery\AiCad\Livewire\Admin;

use Illuminate\Database\Eloquent\Builder;
use Tolery\AiCad\Models\StepMessage;
use Ultraviolettes\FluxDataTable\Livewire\FluxDataTable;

class StepMessageTable extends FluxDataTable
{
    public ?string $sortBy = 'sort_order';

    public function builder(): Builder
    {
        return StepMessage::query()
            ->orderBy('sort_order')
            ->orderBy('step_key');
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
                'label' => 'Cle',
                'field' => 'step_key',
                'searchable' => true,
                'sortable' => true,
            ],
            [
                'label' => 'Libelle',
                'field' => 'label',
                'searchable' => true,
                'sortable' => true,
            ],
            [
                'label' => 'Messages',
                'field' => 'messages',
                'render' => fn ($row) => count($row->messages ?? []).' message(s)',
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
                'render' => fn ($row) => view('ai-cad::livewire.admin.partials.step-message-actions', ['stepMessage' => $row])->render(),
            ],
        ];
    }
}
