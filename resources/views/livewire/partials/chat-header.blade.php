<header class="flex items-center gap-2.5 px-6 pt-8 pb-6 border-b border-grey-stroke bg-white rounded-tl-4xl shrink-0">
    <div
        x-data="{ editing: false, name: @entangle('partName').live, originalName: '{{ $chat->name }}' }"
        class="flex-1">
        <div x-show="editing" class="flex items-center gap-2">
            <input
                x-model="name"
                @keydown.enter="editing = false"
                @keydown.escape="editing = false; name = originalName"
                type="text"
                class="flex-1 text-xl font-semibold text-black bg-transparent border-b-2 border-violet-600 focus:ring-0 focus:outline-none px-2 py-1"
                x-ref="titleInput"
            />
            <button
                @click="editing = false"
                class="shrink-0 w-8 h-8 rounded-full bg-violet-600 hover:bg-violet-700 flex items-center justify-center transition-colors">
                <svg class="w-5 h-5 text-white" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M5 10L8.5 13.5L15 7" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </button>
        </div>
        <span
            x-show="!editing"
            @click="editing = true; originalName = name; $nextTick(() => $refs.titleInput?.focus())"
            class="inline-flex items-center gap-2 text-base font-semibold text-black cursor-pointer hover:text-violet-600 transition-colors">
            <span x-text="name" class="text-xl"></span>
            <svg class="w-5 h-5 text-violet-600" viewBox="0 0 20 20" fill="none" aria-hidden="true">
                <path d="M12.8 3.2a1.6 1.6 0 0 1 2.26 0l1.74 1.74a1.6 1.6 0 0 1 0 2.26l-8.1 8.1a1.6 1.6 0 0 1-.76.42l-3.3.74a.4.4 0 0 1-.48-.48l.74-3.3a1.6 1.6 0 0 1 .42-.76l8.1-8.1Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                <path d="M11.25 4.75 15.25 8.75" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
        </span>
    </div>

    {{-- Quota Display --}}
    @if ($quotaStatus)
        <div class="flex items-center gap-3 px-4 py-2 bg-gray-50 dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 shrink-0">
            <div class="flex items-center gap-2">
                <img src="{{ Vite::asset('resources/images/tolerycad-large-logo.svg') }}" alt="ToleryCAD" class="h-5" />
                @if ($quotaStatus['total'] === -1)
                    <span class="text-sm text-gray-700 dark:text-gray-300">
                        {{ $quotaStatus['used'] }} fichier(s)
                    </span>
                    <flux:badge color="green" size="sm">Illimité</flux:badge>
                @else
                    <span class="text-sm font-medium text-gray-700 dark:text-gray-300">
                        {{ $quotaStatus['used'] }}/{{ $quotaStatus['total'] }}
                    </span>
                    <div class="w-24 bg-gray-200 dark:bg-gray-700 rounded-full h-1.5">
                        <div class="bg-violet-600 h-1.5 rounded-full transition-all" style="width: {{ min(100, ($quotaStatus['used'] / max(1, $quotaStatus['total'])) * 100) }}%"></div>
                    </div>
                @endif
            </div>
        </div>
    @endif

    {{-- History Panel Component --}}
    <livewire:chat-history-panel />
</header>
