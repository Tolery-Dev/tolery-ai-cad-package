<flux:modal name="purchase-or-subscribe" :open="$showPurchaseModal" wire:model="showPurchaseModal" class="space-y-6 min-w-[32rem]">
    <div class="space-y-6">
        <div>
            <flux:heading size="lg" class="mb-2">Débloquer ce fichier CAO</flux:heading>
            <flux:subheading>
                @if($downloadStatus && $downloadStatus['reason'] === 'no_subscription')
                    Vous devez être abonné ou acheter ce fichier pour le télécharger.
                @elseif($downloadStatus && $downloadStatus['reason'] === 'quota_exceeded')
                    Votre quota mensuel est épuisé ({{ $downloadStatus['total_quota'] }}/{{ $downloadStatus['total_quota'] }} fichiers).
                @else
                    Vous n'avez pas accès à ce téléchargement.
                @endif
            </flux:subheading>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            @if($downloadStatus && isset($downloadStatus['options']['can_subscribe']) && $downloadStatus['options']['can_subscribe'])
                <flux:card class="border-2 border-violet-200">
                    <div class="flex flex-col h-full">
                        <div class="flex-1">
                            <flux:heading size="base" class="mb-2 text-violet-600">
                                S'abonner
                            </flux:heading>
                            <flux:subheading class="mb-4">
                                Accès illimité aux téléchargements selon votre plan
                            </flux:subheading>
                            <ul class="space-y-2 text-sm text-zinc-600">
                                <li class="flex items-start gap-2">
                                    <flux:icon.check class="size-4 text-green-600 shrink-0 mt-0.5" />
                                    <span>Plusieurs fichiers par mois</span>
                                </li>
                                <li class="flex items-start gap-2">
                                    <flux:icon.check class="size-4 text-green-600 shrink-0 mt-0.5" />
                                    <span>Accès prioritaire au support</span>
                                </li>
                                <li class="flex items-start gap-2">
                                    <flux:icon.check class="size-4 text-green-600 shrink-0 mt-0.5" />
                                    <span>Nouvelles fonctionnalités en avant-première</span>
                                </li>
                            </ul>
                        </div>
                        <flux:button
                            wire:click="redirectToSubscription"
                            variant="primary"
                            class="mt-4 w-full !bg-violet-600 hover:!bg-violet-700">
                            Voir les plans
                        </flux:button>
                    </div>
                </flux:card>
            @endif

            @if($downloadStatus && isset($downloadStatus['options']['can_purchase']) && $downloadStatus['options']['can_purchase'])
                <flux:card class="border-2 border-zinc-200">
                    <div class="flex flex-col h-full">
                        <div class="flex-1">
                            <flux:heading size="base" class="mb-2">
                                Acheter ce fichier
                            </flux:heading>
                            <flux:subheading class="mb-4">
                                Paiement unique pour ce fichier uniquement
                            </flux:subheading>
                            @if(isset($downloadStatus['options']['purchase_price']))
                                <div class="text-3xl font-bold text-zinc-900 mb-2">
                                    {{ number_format($downloadStatus['options']['purchase_price'] / 100, 2) }}€
                                </div>
                            @endif
                            <ul class="space-y-2 text-sm text-zinc-600">
                                <li class="flex items-start gap-2">
                                    <flux:icon.check class="size-4 text-green-600 shrink-0 mt-0.5" />
                                    <span>Téléchargement immédiat</span>
                                </li>
                                <li class="flex items-start gap-2">
                                    <flux:icon.check class="size-4 text-green-600 shrink-0 mt-0.5" />
                                    <span>Fichier STEP haute qualité</span>
                                </li>
                                <li class="flex items-start gap-2">
                                    <flux:icon.check class="size-4 text-green-600 shrink-0 mt-0.5" />
                                    <span>Accès illimité à ce fichier</span>
                                </li>
                            </ul>
                        </div>
                        <flux:button
                            wire:click="purchaseFile"
                            variant="outline"
                            class="mt-4 w-full">
                            Acheter maintenant
                        </flux:button>
                    </div>
                </flux:card>
            @endif
        </div>
    </div>

    <div class="flex gap-2 justify-end pt-6 border-t border-zinc-200">
        <flux:modal.close>
            <flux:button variant="ghost">
                Annuler
            </flux:button>
        </flux:modal.close>
    </div>
</flux:modal>
