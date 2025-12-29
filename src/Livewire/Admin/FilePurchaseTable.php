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
            [
                'label' => 'Actions',
                'field' => 'actions',
                'render' => function ($row) {
                    if (! $row->chat) {
                        return '-';
                    }

                    $disk = Storage::disk(config('ai-cad.storage_disk', 's3'));
                    $useS3 = method_exists($disk->getAdapter(), 'temporaryUrl');

                    $downloadUrl = $useS3
                        ? URL::temporarySignedRoute('ai-cad.admin.download.s3', now()->addMinutes(5), ['chat' => $row->chat->id])
                        : URL::temporarySignedRoute('ai-cad.admin.download', now()->addMinutes(5), ['chat' => $row->chat->id]);

                    return '<a href="'.e($downloadUrl).'" target="_blank" class="inline-flex items-center gap-1 rounded-md bg-blue-600 px-2.5 py-1.5 text-xs font-medium text-white hover:bg-blue-700">'
                        .'<svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>'
                        .'ZIP'
                        .'</a>';
                },
            ],
        ];
    }
}
