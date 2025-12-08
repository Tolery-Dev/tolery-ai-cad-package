<header class="flex items-center gap-2.5 px-6 pt-8 pb-6 border-b border-grey-stroke bg-white rounded-tl-4xl shrink-0">
    <button
        onclick="window.location.href='{{ back() }}'"
        class="w-8 h-8 border border-[#CECECE] rounded flex items-center justify-center hover:bg-gray-50 transition-colors shrink-0">
        <svg class="w-4 h-4 text-[#323232]" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path d="M15 18L9 12L15 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
    </button>
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

    {{-- History Panel Component --}}
    <livewire:chat-history-panel />
</header>
