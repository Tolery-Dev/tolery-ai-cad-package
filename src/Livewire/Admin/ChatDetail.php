<?php

namespace Tolery\AiCad\Livewire\Admin;

use Illuminate\View\View;
use Livewire\Component;
use Tolery\AiCad\Models\Chat;

class ChatDetail extends Component
{
    public Chat $chat;

    public function mount(Chat $chat): void
    {
        $this->chat = $chat->load(['messages.user', 'team']);
    }

    public function render(): View
    {
        return view('ai-cad::livewire.admin.chat-detail');
    }
}
