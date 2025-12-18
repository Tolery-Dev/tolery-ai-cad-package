<header class="flex items-center justify-between gap-2.5 px-6 pt-8 pb-6 border-b border-grey-stroke bg-white rounded-tl-4xl shrink-0">
    <div
        x-data="{ editing: false, name: @entangle('partName').live, originalName: '{{ $chat->name }}' }"
        >
        <div x-show="editing" class="flex items-center justify-between gap-4">
            <flux:input
                x-model="name"
                @keydown.enter="editing = false"
                @keydown.escape="editing = false; name = originalName"
                type="text"
                x-ref="titleInput"
            />
            <flux:button icon="check" @click="editing = false" inset />
        </div>
        <div
            x-show="!editing"
            @click="editing = true; originalName = name; $nextTick(() => $refs.titleInput?.focus())"
            class="flex items-center justify-between gap-4">
            <flux:heading size="xl" x-text="name" />
            <flux:button icon:class="text-violet-600" icon="pencil" variant="ghost"/>
        </div>
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
