<header class="flex items-center gap-2.5 px-6 pt-8 pb-6 border-b border-grey-stroke bg-white rounded-tl-4xl shrink-0">
    <button
        onclick="window.location.href='{{ back() }}'"
        class="w-8 h-8 border border-[#CECECE] rounded flex items-center justify-center hover:bg-gray-50 transition-colors shrink-0">
        <svg class="w-4 h-4 text-[#323232]" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path d="M15 18L9 12L15 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
    </button>
    <div
        x-data="{ editing: false, name: @entangle('partName').live }"
        class="flex-1">
        <input
            x-show="editing"
            x-model="name"
            @blur="editing = false"
            @keydown.enter="editing = false"
            @keydown.escape="editing = false; name = '{{ $chat->name }}'"
            type="text"
            class="text-sm font-semibold text-black bg-transparent border-none focus:ring-0 focus:outline-none p-0 w-full"
        />
        <span
            x-show="!editing"
            @click="editing = true"
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
