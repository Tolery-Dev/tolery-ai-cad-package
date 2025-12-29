<div>
    <flux:card>
        <form wire:submit="save" class="space-y-6">
            <flux:field>
                <flux:label>Nom</flux:label>
                <flux:input wire:model="name" placeholder="Ex: Créer une pièce circulaire" />
                <flux:error name="name" />
            </flux:field>

            <flux:field>
                <flux:label>Texte du prompt</flux:label>
                <flux:textarea wire:model="prompt_text" rows="6" placeholder="Le texte qui sera envoyé comme prompt..." />
                <flux:error name="prompt_text" />
            </flux:field>

            <flux:field>
                <flux:label>Famille de matériau (optionnel)</flux:label>
                <flux:select wire:model="material_family">
                    <flux:select.option value="">Tous les matériaux</flux:select.option>
                    @foreach($this->materialFamilies as $family)
                        <flux:select.option value="{{ $family['value'] }}">{{ $family['label'] }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:description>Si sélectionné, ce prompt ne sera affiché que pour cette famille de matériau.</flux:description>
                <flux:error name="material_family" />
            </flux:field>

            <div class="grid grid-cols-2 gap-4">
                <flux:field>
                    <flux:label>Ordre d'affichage</flux:label>
                    <flux:input type="number" wire:model="sort_order" min="0" />
                    <flux:error name="sort_order" />
                </flux:field>

                <flux:field>
                    <flux:label>Statut</flux:label>
                    <div class="mt-2">
                        <flux:switch wire:model="active" label="Actif" />
                    </div>
                    <flux:error name="active" />
                </flux:field>
            </div>

            <div class="flex justify-end gap-3">
                <flux:button variant="ghost" href="{{ route('ai-cad.admin.prompts.index') }}">
                    Annuler
                </flux:button>
                <flux:button type="submit" variant="primary">
                    {{ $prompt?->exists ? 'Mettre à jour' : 'Créer' }}
                </flux:button>
            </div>
        </form>
    </flux:card>
</div>
