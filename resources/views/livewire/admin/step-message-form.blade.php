<div>
    <flux:card>
        <form wire:submit="save" class="space-y-6">
            <flux:field>
                <flux:label>Clé de l'étape</flux:label>
                <flux:input wire:model="step_key" placeholder="Ex: analysis, parameters, generation_code, export, complete" />
                <flux:description>Identifiant unique de l'étape (analysis, parameters, generation_code, export, complete).</flux:description>
                <flux:error name="step_key" />
            </flux:field>

            <flux:field>
                <flux:label>Libellé</flux:label>
                <flux:input wire:model="label" placeholder="Ex: Analyse des dimensions" />
                <flux:description>Libellé affiché dans l'interface d'administration.</flux:description>
                <flux:error name="label" />
            </flux:field>

            <flux:field>
                <flux:label>Messages</flux:label>
                <flux:textarea wire:model="messages_text" rows="6" placeholder="Un message par ligne...&#10;Analyse des dimensions de la pièce...&#10;Vérification des contraintes de fabrication...&#10;Validation de la géométrie..." />
                <flux:description>Les messages affichés pendant cette étape (un par ligne). Ils seront affichés en boucle.</flux:description>
                <flux:error name="messages_text" />
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
                <flux:button variant="ghost" href="{{ route('ai-cad.admin.step-messages.index') }}">
                    Annuler
                </flux:button>
                <flux:button type="submit" variant="primary">
                    {{ $stepMessage?->exists ? 'Mettre à jour' : 'Créer' }}
                </flux:button>
            </div>
        </form>
    </flux:card>
</div>
