<div class="flex items-center gap-3">
    {{-- Thumbnail --}}
    @php
        $screenshotUrl = $chat->getLatestScreenshotUrl();
    @endphp
    <div class="shrink-0">
        @if($screenshotUrl)
            <img src="{{ $screenshotUrl }}"
                 alt="{{ $chat->name }}"
                 class="size-10 rounded-lg object-cover border border-zinc-200 dark:border-zinc-700">
        @else
            <div class="size-10 rounded-lg bg-zinc-100 dark:bg-zinc-800 flex items-center justify-center">
                <svg class="size-5 text-zinc-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                </svg>
            </div>
        @endif
    </div>

    {{-- Name & Session ID --}}
    <div class="flex flex-col gap-0.5 min-w-0">
        <a href="{{ route('ai-cad.admin.chats.show', $chat) }}"
           class="font-medium text-zinc-900 dark:text-zinc-100 hover:text-violet-600 dark:hover:text-violet-400 transition-colors truncate">
            {{ $chat->name ?: 'Sans nom' }}
        </a>
        @if($chat->session_id)
            <div x-data="{ copied: false }" class="flex items-center gap-1.5">
                <code class="text-xs font-mono text-zinc-400 dark:text-zinc-500 truncate max-w-32">
                    {{ Str::limit($chat->session_id, 16) }}
                </code>
                <button
                    type="button"
                    @click.stop="navigator.clipboard.writeText('{{ $chat->session_id }}'); copied = true; setTimeout(() => copied = false, 1500)"
                    class="p-0.5 rounded hover:bg-zinc-100 dark:hover:bg-zinc-700 text-zinc-400 hover:text-zinc-600 dark:hover:text-zinc-300 transition-colors shrink-0"
                    title="Copier le session ID">
                    <svg x-show="!copied" class="size-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                    </svg>
                    <svg x-show="copied" x-cloak class="size-3.5 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                    </svg>
                </button>
            </div>
        @endif
    </div>
</div>
