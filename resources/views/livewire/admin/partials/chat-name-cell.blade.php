<div class="flex flex-col gap-1">
    <a href="{{ route('ai-cad.admin.chats.show', $chat) }}"
       class="font-medium text-zinc-900 dark:text-zinc-100 hover:text-violet-600 dark:hover:text-violet-400 transition-colors">
        {{ $chat->name ?: 'Sans nom' }}
    </a>
    @if($chat->session_id)
        <div x-data="{ copied: false }" class="flex items-center gap-1.5">
            <code class="text-xs font-mono text-zinc-400 dark:text-zinc-500 bg-zinc-50 dark:bg-zinc-800/50 px-1.5 py-0.5 rounded">
                {{ Str::limit($chat->session_id, 20) }}
            </code>
            <button
                type="button"
                @click.stop="navigator.clipboard.writeText('{{ $chat->session_id }}'); copied = true; setTimeout(() => copied = false, 1500)"
                class="p-0.5 rounded hover:bg-zinc-100 dark:hover:bg-zinc-700 text-zinc-400 hover:text-zinc-600 dark:hover:text-zinc-300 transition-colors"
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
