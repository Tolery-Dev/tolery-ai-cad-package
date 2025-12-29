<?php

namespace Tolery\AiCad\Livewire\Admin;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Number;
use Tolery\AiCad\Models\FilePurchase;
use Ultraviolettes\FluxDataTable\Livewire\FluxDataTable;

class FilePurchaseTable extends FluxDataTable
{
    public ?string $sortBy = 'purchased_at';

    public string $sortDirection = 'desc';

    public function builder(): Builder
    {
        return FilePurchase::query()
            ->with(['team', 'chat']);
    }

    public function columns(): array
    {
        return [
            [
                'label' => 'Date',
                'field' => 'purchased_at',
                'sortable' => true,
                'render' => fn ($row) => $row->purchased_at?->format('d/m/Y H:i') ?? '-',
            ],
            [
                'label' => 'Équipe',
                'field' => 'team_id',
                'render' => fn ($row) => $row->team->name ?? '-',
            ],
            [
                'label' => 'Montant',
                'field' => 'amount',
                'sortable' => true,
                'render' => fn ($row) => Number::currency($row->amount / 100, 'EUR', 'fr'),
            ],
            [
                'label' => 'Conversation',
                'field' => 'chat_id',
                'render' => fn ($row) => $row->chat
                    ? '<a href="'.route('ai-cad.admin.chats.show', $row->chat).'" class="text-blue-600 hover:underline">'
                        .e($row->chat->name ?: 'Chat #'.$row->chat_id)
                        .'</a>'
                    : '-',
            ],
            [
                'label' => 'Stripe ID',
                'field' => 'stripe_payment_intent_id',
                'render' => fn ($row) => $row->stripe_payment_intent_id
                    ? '<span class="font-mono text-xs">'.substr($row->stripe_payment_intent_id, 0, 20).'...</span>'
                    : '-',
            ],
        ];
    }
}
