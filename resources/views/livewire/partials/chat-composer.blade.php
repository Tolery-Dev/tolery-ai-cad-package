<footer class="bg-white shrink-0" x-data="{ hasContent: false }">
    <form wire:submit.prevent="send" class="px-6 pb-2 pt-6">
        <div class="flex flex-col gap-2">
            {{-- Container pour les chips de sélection de faces --}}
            <div
                id="face-selection-chips"
                data-face-selection-chips
                class="hidden flex flex-wrap gap-2 mb-2">
            </div>

            <flux:composer
                wire:model="message"
                submit="send"
                placeholder="Décrivez le plus précisément votre pièce ou insérez un lien url ici"
                x-data="{ resize() { $el.style.height = 'auto'; $el.style.height = $el.scrollHeight + 'px'; } }"
                x-init="resize()"
                x-on:input="resize(); $dispatch('composer-input', { value: $event.target.value })"
                x-on:keydown.enter="if (!$event.shiftKey) { $event.preventDefault(); $wire.send() }"
                @composer-input.window="hasContent = $event.detail.value?.trim().length > 0">
            </flux:composer>

            <div class="flex items-center justify-between">
                <div class="flex items-center gap-4">
                </div>

                <button
                    type="submit"
                    class="cursor-pointer w-6 h-6 hover:opacity-80 transition-all flex items-center justify-center"
                    :class="hasContent ? 'scale-110' : ''">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" class="w-6 h-6 transition-colors">
                        <g clip-path="url(#clip0_2847_31394)">
                            <path d="M3.4 20.3995L20.85 12.9195C21.66 12.5695 21.66 11.4295 20.85 11.0795L3.4 3.59953C2.74 3.30953 2.01 3.79953 2.01 4.50953L2 9.11953C2 9.61953 2.37 10.0495 2.87 10.1095L17 11.9995L2.87 13.8795C2.37 13.9495 2 14.3795 2 14.8795L2.01 19.4895C2.01 20.1995 2.74 20.6895 3.4 20.3995Z" :fill="hasContent ? '#7C3AED' : '#565C66'"/>
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
