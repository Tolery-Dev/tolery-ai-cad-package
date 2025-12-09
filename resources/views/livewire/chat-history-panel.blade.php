<div>
    {{-- Trigger button (will be placed in the header) --}}
    <flux:button
        wire:click="openPanel"
        variant="ghost"
        size="sm"
        icon="clock"
    >
        Historique
    </flux:button>

    {{-- Slide-over panel --}}
    <flux:modal name="chat-history-panel" :open="$showPanel" wire:model="showPanel" variant="flyout" class="w-full max-w-md">
        <div class="flex flex-col h-full">
            {{-- Header --}}
            <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between">
                <flux:heading size="lg">Mes fichiers CAO</flux:heading>
            </div>

            {{-- Content --}}
            <div class="flex-1 overflow-y-auto p-4 space-y-3">
                @forelse ($this->history as $item)
                    @php
                        $chat = $item['chat'];
                        $latestMessage = $chat->messages->first();
                        $screenshotUrl = $latestMessage?->getScreenshotUrl();
                    @endphp
                    <div class="bg-white rounded-lg border border-gray-200 overflow-hidden hover:shadow-md transition-shadow">
                        <div class="flex gap-3 p-3">
                            {{-- Thumbnail --}}
                            <div class="w-20 h-16 rounded bg-gray-100 flex-shrink-0 overflow-hidden">
                                @if ($screenshotUrl)
                                    <img src="{{ $screenshotUrl }}" alt="{{ $chat->name }}" class="w-full h-full object-cover">
                                @else
                                    <div class="w-full h-full flex items-center justify-center">
                                        <flux:icon.cube class="size-6 text-gray-300" />
                                    </div>
                                @endif
                            </div>

                            {{-- Info --}}
                            <div class="flex-grow min-w-0">
                                <p class="font-medium text-sm text-gray-900 truncate">
                                    {{ $chat->name ?? 'Fichier CAO #' . $chat->id }}
                                </p>
                                <p class="text-xs text-gray-500 mt-0.5">
                                    {{ $item['date']->format('d/m/Y H:i') }}
                                </p>
                                <div class="mt-1">
                                    @if ($item['type'] === 'subscription')
                                        <flux:badge color="blue" size="sm">Abonnement</flux:badge>
                                    @else
                                        <flux:badge color="green" size="sm">Achat {{ \Illuminate\Support\Number::currency($item['amount'] / 100, 'EUR') }}</flux:badge>
                                    @endif
                                </div>
                            </div>

                            {{-- Download button --}}
                            <div class="flex-shrink-0 self-center">
                                <flux:button
                                    wire:click="downloadFile({{ $chat->id }})"
                                    variant="ghost"
                                    size="sm"
                                    icon="arrow-down-tray"
                                    class="text-violet-600 hover:text-violet-700"
                                />
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="text-center py-12">
                        <flux:icon.inbox class="size-12 text-gray-300 mx-auto mb-4" />
                        <flux:text class="text-gray-500">Aucun fichier telecharge</flux:text>
                        <flux:text class="text-sm text-gray-400 mt-1">
                            Vos fichiers CAO apparaitront ici après téléchargement.
                        </flux:text>
                    </div>
                @endforelse
            </div>

            {{-- Footer --}}
            @if ($this->history->isNotEmpty())
                <div class="px-6 py-4 border-t border-gray-200 bg-gray-50">
                    <flux:text class="text-xs text-gray-500 text-center">
                        {{ $this->history->count() }} fichier(s) disponible(s)
                    </flux:text>
                </div>
            @endif
        </div>
    </flux:modal>
</div>
