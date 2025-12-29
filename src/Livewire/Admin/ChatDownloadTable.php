<?php

namespace Tolery\AiCad\Livewire\Admin;

use Illuminate\Database\Eloquent\Builder;
use Tolery\AiCad\Models\ChatDownload;
use Ultraviolettes\FluxDataTable\Livewire\FluxDataTable;

class ChatDownloadTable extends FluxDataTable
{
    public ?string $sortBy = 'downloaded_at';

    public string $sortDirection = 'desc';

    public function builder(): Builder
    {
        return ChatDownload::query()
            ->with(['team', 'chat', 'message']);
    }

    public function columns(): array
    {
        return [
            [
                'label' => 'Date',
                'field' => 'downloaded_at',
                'sortable' => true,
                'render' => fn ($row) => $row->downloaded_at?->format('d/m/Y H:i') ?? '-',
            ],
            [
                'label' => 'Équipe',
                'field' => 'team_id',
                'render' => fn ($row) => $row->team->name ?? '-',
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
                'label' => 'Type fichier',
                'field' => 'file_type',
                'render' => fn ($row) => $row->file_type
                    ? '<span class="font-mono text-xs uppercase">'.e($row->file_type).'</span>'
                    : '-',
            ],
            [
                'label' => 'Message',
                'field' => 'message_id',
                'render' => fn ($row) => $row->message_id ? '#'.$row->message_id : '-',
            ],
        ];
    }
}
