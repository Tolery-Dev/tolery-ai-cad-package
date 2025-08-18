<?php

namespace Tolery\AiCad\Livewire;

use Livewire\Component;
use Tolery\AiCad\Livewire\Forms\ChatForm;
use Tolery\AiCad\Models\Chat;

class ChatConfig extends Component
{
    public Chat $chat;

    public ChatForm $form;

    public function mount()
    {
        $this->form->setChat($this->chat);
    }

    public function save()
    {
        $this->form->update();
    }

    public function render()
    {
        return view('ai-cad::livewire.chat-config'); // @phpstan-ignore-line
    }
}
