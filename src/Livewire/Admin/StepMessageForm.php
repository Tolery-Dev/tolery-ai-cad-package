<?php

namespace Tolery\AiCad\Livewire\Admin;

use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Livewire\Component;
use Tolery\AiCad\Models\StepMessage;

class StepMessageForm extends Component
{
    public ?StepMessage $stepMessage = null;

    public string $step_key = '';

    public string $label = '';

    public string $messages_text = '';

    public bool $active = true;

    public int $sort_order = 0;

    public function mount(?StepMessage $stepMessage = null): void
    {
        if ($stepMessage && $stepMessage->exists) {
            $this->stepMessage = $stepMessage;
            $this->step_key = $stepMessage->step_key;
            $this->label = $stepMessage->label;
            $this->messages_text = implode("\n", $stepMessage->messages ?? []);
            $this->active = $stepMessage->active;
            $this->sort_order = $stepMessage->sort_order;
        }
    }

    public function save(): void
    {
        // Verifier les autorisations
        if ($this->stepMessage && $this->stepMessage->exists) {
            $this->authorize('update', $this->stepMessage);
        } else {
            $this->authorize('create', StepMessage::class);
        }

        $validated = $this->validate([
            'step_key' => ['required', 'string', 'in:analysis,parameters,generation_code,export,complete'],
            'label' => ['required', 'string', 'max:255'],
            'messages_text' => ['required', 'string'],
            'active' => ['boolean'],
            'sort_order' => ['integer', 'min:0'],
        ]);

        // Convertir les lignes en tableau de messages
        $messagesArray = array_values(array_filter(
            array_map('trim', explode("\n", $validated['messages_text'])),
            fn ($line) => $line !== ''
        ));

        $data = [
            'step_key' => $validated['step_key'],
            'label' => $validated['label'],
            'messages' => $messagesArray,
            'active' => $validated['active'],
            'sort_order' => $validated['sort_order'],
        ];

        // Executer dans une transaction
        DB::transaction(function () use ($data) {
            if ($this->stepMessage && $this->stepMessage->exists) {
                $this->stepMessage->update($data);
                session()->flash('success', 'Message d\'étape mis à jour avec succès.');
            } else {
                StepMessage::create($data);
                session()->flash('success', 'Message d\'étape créé avec succès.');
            }
        });

        $this->redirect(route('ai-cad.admin.step-messages.index'));
    }

    public function render(): View
    {
        return view('ai-cad::livewire.admin.step-message-form');
    }
}
