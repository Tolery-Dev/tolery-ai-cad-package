<?php

namespace Tolery\AiCad\Livewire\Admin;

use Illuminate\View\View;
use Livewire\Component;
use Tolery\AiCad\Enum\MaterialFamily;
use Tolery\AiCad\Models\PredefinedPrompt;

class PredefinedPromptForm extends Component
{
    public ?PredefinedPrompt $prompt = null;

    public string $name = '';

    public string $prompt_text = '';

    public ?string $material_family = null;

    public bool $active = true;

    public int $sort_order = 0;

    public function mount(?PredefinedPrompt $prompt = null): void
    {
        if ($prompt && $prompt->exists) {
            $this->prompt = $prompt;
            $this->name = $prompt->name;
            $this->prompt_text = $prompt->prompt_text;
            $this->material_family = $prompt->material_family?->value;
            $this->active = $prompt->active;
            $this->sort_order = $prompt->sort_order;
        }
    }

    public function save(): void
    {
        // Vérifier les autorisations
        if ($this->prompt && $this->prompt->exists) {
            $this->authorize('update', $this->prompt);
        } else {
            $this->authorize('create', PredefinedPrompt::class);
        }

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'prompt_text' => ['required', 'string'],
            'material_family' => ['nullable', 'string'],
            'active' => ['boolean'],
            'sort_order' => ['integer', 'min:0'],
        ]);

        $data = [
            'name' => $validated['name'],
            'prompt_text' => $validated['prompt_text'],
            'material_family' => $validated['material_family'] ? MaterialFamily::from($validated['material_family']) : null,
            'active' => $validated['active'],
            'sort_order' => $validated['sort_order'],
        ];

        // Exécuter dans une transaction
        DB::transaction(function () use ($data) {
            if ($this->prompt && $this->prompt->exists) {
                $this->prompt->update($data);
                session()->flash('success', 'Prompt mis à jour avec succès.');
            } else {
                PredefinedPrompt::create($data);
                session()->flash('success', 'Prompt créé avec succès.');
            }
        });

        $this->redirect(route('ai-cad.admin.prompts.index'));
    }

    public function getMaterialFamiliesProperty(): array
    {
        return collect(MaterialFamily::cases())
            ->map(fn ($family) => [
                'value' => $family->value,
                'label' => $family->label(),
            ])
            ->toArray();
    }

    public function render(): View
    {
        return view('ai-cad::livewire.admin.predefined-prompt-form');
    }
}
