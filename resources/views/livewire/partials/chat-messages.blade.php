@forelse ($messages ?? [] as $msg)
    <article class="flex items-start gap-3 mb-4 {{ $msg['role'] === 'user' ? 'flex-row-reverse' : '' }}">
        <div class="h-8 w-8 shrink-0 rounded-full grid place-items-center {{ $msg['role'] === 'user' ? 'bg-violet-300 text-white' : 'dark:bg-zinc-800 dark:text-zinc-200' }}">
            @if($msg['role'] === 'user')
                👤
            @else
                <img src="{{ Vite::asset('resources/images/chat-icon.png') }}" alt="" class="w-7 h-7">
            @endif
        </div>
        <div class="flex-1 {{ $msg['role'] === 'user' ? 'text-right' : '' }}">
            <div class="text-xs text-gray-500 dark:text-zinc-400 mb-1">
                {{ $msg['role'] === 'user' ? 'Vous' : 'Tolery' }}
                <span class="mx-1">•</span>
                <time>{{ \Illuminate\Support\Carbon::parse($msg['created_at'] ?? now())->format('H:i') }}</time>
            </div>
            <div class="{{ $msg['role'] === 'user' ? 'inline-block border border-gray-100 bg-gray-50 dark:border-zinc-700 dark:bg-zinc-800' : 'inline-block bg-gray-100 dark:bg-zinc-800 text-gray-900 dark:text-zinc-100' }} rounded-xl px-3 py-2">
                {!! nl2br(e($msg['content'] ?? '')) !!}
            </div>
        </div>
    </article>
@empty
@endforelse
