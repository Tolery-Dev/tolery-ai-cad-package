<flux:modal name="purchase-or-subscribe" :open="$showPurchaseModal" wire:model="showPurchaseModal" class="space-y-6 min-w-[32rem]">
    <div class="space-y-6">
        <div>
            <flux:heading size="lg" class="mb-2">Télécharger ce fichier CAO</flux:heading>
            <flux:subheading>
                @if($downloadStatus && $downloadStatus['reason'] === 'no_subscription')
                    Vous devez être abonné ou acheter ce fichier pour le télécharger.
                @elseif($downloadStatus && $downloadStatus['reason'] === 'quota_exceeded')
                    Votre quota mensuel est épuisé ({{ $downloadStatus['total_quota'] }}/{{ $downloadStatus['total_quota'] }} fichiers utilisés).
                    <br>
                    <span class="text-violet-600 font-medium">Passez à un plan supérieur ou achetez ce fichier individuellement.</span>
                @else
                    Vous n'avez pas accès à ce téléchargement.
                @endif
            </flux:subheading>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            @if($downloadStatus && isset($downloadStatus['options']['can_subscribe']) && $downloadStatus['options']['can_subscribe'])
                <flux:card class="border-2 border-violet-200 transition-colors hover:border-violettes cursor-pointer">
                    <div class="flex flex-col h-full">
                        <div class="flex-1">
                            <flux:heading size="base" class="mb-2 text-violet-600">
                                Abonnement
                            </flux:heading>
                            <flux:subheading class="mb-4">
                                Téléchargez plusieurs fichiers CAO par mois selon votre plan
                            </flux:subheading>
                            <ul class="space-y-2 text-sm text-zinc-600">
                                <li class="flex items-start gap-2">
                                    <flux:icon.check class="size-4 text-green-600 shrink-0 mt-0.5" />
                                    <span>Prix par fichier dégressif</span>
                                </li>
                                <li class="flex items-start gap-2">
                                    <flux:icon.check class="size-4 text-green-600 shrink-0 mt-0.5" />
                                    <span>Téléchargement immédiat</span>
                                </li>
                                <li class="flex items-start gap-2">
                                    <flux:icon.check class="size-4 text-green-600 shrink-0 mt-0.5" />
                                    <span>Accès illimité à ce fichier</span>
                                </li>
                            </ul>
                        </div>
                        <flux:button
                            wire:click="redirectToSubscription"
                            variant="primary"
                            color="purple"
                            class="mt-4 w-full">
                            @if($downloadStatus && $downloadStatus['reason'] === 'quota_exceeded')
                                Passer à un plan supérieur
                            @else
                                S'abonner
                            @endif
                        </flux:button>
                    </div>
                </flux:card>
            @endif

            @if($downloadStatus && isset($downloadStatus['options']['can_purchase']) && $downloadStatus['options']['can_purchase'])
                <flux:card class="border-2 border-violet-200 transition-colors hover:border-violettes cursor-pointer">
                    <div class="flex flex-col h-full">
                        <div class="flex-1">
                            <flux:heading size="base" class="mb-2">
                                Sans abonnement
                            </flux:heading>
                            <flux:subheading class="mb-4">
                                Paiement unique pour ce fichier uniquement
                            </flux:subheading>
                            @if(isset($downloadStatus['options']['purchase_price']))
                                @php
                                    $ht = $downloadStatus['options']['purchase_price'];
                                    $tva = round($ht * 0.20);
                                    $ttc = $ht + $tva;
                                @endphp
                                <div class="mb-4 space-y-1">
                                    <div class="flex justify-between text-sm text-zinc-700">
                                        <span>Montant fichier CAO</span>
                                        <span>{{ number_format($ht / 100, 2, ',', ' ') }} € HT</span>
                                    </div>
                                    <div class="flex justify-between text-sm text-zinc-500">
                                        <span>TVA 20%</span>
                                        <span>{{ number_format($tva / 100, 2, ',', ' ') }} €</span>
                                    </div>
                                    <div class="flex justify-between text-base font-bold text-violettes pt-1 border-t border-zinc-200">
                                        <span>Total TTC</span>
                                        <span>{{ number_format($ttc / 100, 2, ',', ' ') }} €</span>
                                    </div>
                                </div>
                            @endif
                            <ul class="space-y-2 text-sm text-zinc-600">
                                <li class="flex items-start gap-2">
                                    <flux:icon.check class="size-4 text-green-600 shrink-0 mt-0.5" />
                                    <span>Téléchargement immédiat</span>
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
                            class="mt-4 w-full !border-violettes hover:!bg-purple-100">
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
