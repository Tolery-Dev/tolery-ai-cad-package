<footer class="bg-white shrink-0"
    x-data="{ hasContent: false, busy: false }"
    @cad-generation-started.window="busy = true"
    @cad-generation-ended.window="busy = false"
    @tolery-chat-append.window="busy = false">
    <form wire:submit.prevent="send" class="px-6 pb-6 pt-4" @submit="if (busy) $event.preventDefault()">
        <div class="flex flex-col gap-2">
            {{-- Container pour les chips de sélection de faces --}}
            <div
                wire:ignore
                id="face-selection-chips"
                data-face-selection-chips
                class="hidden flex flex-wrap gap-2 mb-2"
                @face-selection-changed.window="
                    console.log('[DEBUG] Dispatching to Livewire, hasSelection:', $event.detail.hasSelection);
                    $wire.dispatch('face-selection-state-changed', { hasSelection: $event.detail.hasSelection });
                ">
            </div>

            <flux:composer
                wire:key="{{ $composerPlaceholder }}"
                wire:model="message"
                submit="send"
                placeholder="{{ $composerPlaceholder }}"
                x-on:input="$dispatch('composer-input', { value: $event.target.value })"
                x-on:keydown.enter="if (!$event.shiftKey && !busy) { $event.preventDefault(); $wire.send() }"
                x-bind:disabled="busy"
                @composer-input.window="hasContent = $event.detail.value?.trim().length > 0">
            </flux:composer>

            <div class="flex items-center justify-between">
                <div class="flex items-center gap-4">
                    @if(config('app.debug'))
                        <button
                            type="button"
                            wire:click="simulateBotResponse"
                            class="cursor-pointer px-2 py-1 text-xs rounded bg-amber-100 text-amber-700 hover:bg-amber-200 transition-colors"
                            title="Simuler une réponse du bot (debug)">
                            Test typewriter
                        </button>
                    @endif
                </div>

                <button
                    type="submit"
                    :disabled="busy"
                    class="w-6 h-6 transition-all flex items-center justify-center"
                    :class="busy ? 'opacity-40 cursor-not-allowed' : (hasContent ? 'scale-110 cursor-pointer hover:opacity-80' : 'cursor-pointer hover:opacity-80')">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" class="w-6 h-6 transition-colors">
                        <g clip-path="url(#clip0_2847_31394)">
                            <path d="M3.4 20.3995L20.85 12.9195C21.66 12.5695 21.66 11.4295 20.85 11.0795L3.4 3.59953C2.74 3.30953 2.01 3.79953 2.01 4.50953L2 9.11953C2 9.61953 2.37 10.0495 2.87 10.1095L17 11.9995L2.87 13.8795C2.37 13.9495 2 14.3795 2 14.8795L2.01 19.4895C2.01 20.1995 2.74 20.6895 3.4 20.3995Z" :fill="busy ? '#c4c4c4' : (hasContent ? '#7C3AED' : '#565C66')"/>
                        </g>
                        <defs>
                            <clipPath id="clip0_2847_31394">
                                <rect width="24" height="24" fill="white"/>
                            </clipPath>
                        </defs>
                    </svg>
                </button>
            </div>
        </div>
    </form>
</footer>
