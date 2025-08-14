<?php

namespace Tolery\AiCad\Livewire\Forms;

use Illuminate\Validation\Rules\Enum;
use Livewire\Attributes\Validate;
use Livewire\Form;
use Tolery\AiCad\Enum\MaterialFamily;
use Tolery\AiCad\Models\Chat;

class ChatForm extends Form
{
    public ?Chat $chat;

    #[Validate('string')]
    public $name = '';

    #[Validate(['required', new Enum(MaterialFamily::class)])]
    public $materialFamily = '';

    public function setChat(Chat $chat): void
    {
        $this->chat = $chat;

        $this->name = $chat->name;

        $this->materialFamily = $chat->material_family;
    }

    public function update(): void
    {
        $validatedValues = $this->validate();

        $this->chat->update([
            'name' => $validatedValues['name'],
            'material_family' => $validatedValues['materialFamily'],
        ]);
    }
}
