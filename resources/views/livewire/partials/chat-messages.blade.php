@forelse ($messages ?? [] as $msg)
    <article class="flex items-start gap-3 mb-4 {{ $msg['role'] === 'user' ? 'flex-row-reverse' : '' }}"
             data-is-last="{{ $loop->last ? 'true' : 'false' }}">
        <div class="shrink-0">
            @if($msg['role'] === 'user')
                @if(!empty($msg['user']))
                    <x-avatar :user="$msg['user']" size="sm" color="purple" />
                @else
                    <div class="h-8 w-8 rounded-full bg-violet-300 text-white grid place-items-center text-sm font-semibold">
                        ?
                    </div>
                @endif
            @else
                <img src="{{ asset('vendor/ai-cad/images/bot-icon.svg') }}"
                     alt="Tolery Bot"
                     class="h-8 w-8 p-1 bot-avatar"
                     :class="{ 'bot-thinking': isGenerating && $el.closest('article').dataset.isLast === 'true' }">
            @endif
        </div>
        <div class="flex-1 {{ $msg['role'] === 'user' ? 'text-right' : '' }}">
            <div class="text-xs text-gray-500 mb-1 flex items-center gap-1.5 {{ $msg['role'] === 'user' ? 'justify-end' : '' }}">
                <span>{{ $msg['role'] === 'user' ? 'Vous' : 'ToleryCAD' }}</span>

                @if ($msg['role'] === 'assistant' && !empty($msg['version']))
                    <button
                        wire:click="downloadVersion({{ $msg['id'] }})"
                        title="Télécharger cette version"
                        class="inline-flex items-center gap-1 px-1.5 py-0.5 text-xs font-semibold text-purple-700 bg-purple-100 rounded-full hover:bg-purple-200 hover:scale-105 transition-all duration-200 cursor-pointer">
                        <flux:icon.cube-transparent class="size-3" />
                        {{ $msg['version'] }}
                        <flux:icon.arrow-down-tray class="size-3" />
                    </button>
                @endif

                <span class="mx-1">•</span>
                <time>{{ \Illuminate\Support\Carbon::parse($msg['created_at'] ?? now())->format('H:i') }}</time>
            </div>
            <div
                class="{{ $msg['role'] === 'user' ? 'inline-block border border-gray-100 bg-gray-50' : 'inline-block bg-gray-100 text-gray-900' }} rounded-xl px-3 py-2"
                data-message-content="{{ base64_encode($msg['content'] ?? '') }}"
                x-data="{
                    parsedContent: '',
                    parseFaceContext(text) {
                        if (!text) return text;

                        // Parse [FACE_CONTEXT: ...]] patterns
                        return text.replace(/\[FACE_CONTEXT:\s*(.+?)\]\]/g, (match, faceContext) => {
                            // Extract face ID
                            const idMatch = faceContext.match(/ID\[([^\]]+)\]/);
                            const faceId = idMatch ? idMatch[1] : 'Unknown';
                            const label = 'Face ' + faceId;

                            // Return chip HTML with single quotes
                            return `<span class='inline-flex items-center gap-2 px-3 py-1.5 rounded-full border border-violet-200 bg-violet-50 text-violet-700 text-sm font-medium'>` +
                                `<svg class='w-4 h-4' fill='none' stroke='currentColor' viewBox='0 0 24 24'>` +
                                `<path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4'></path>` +
                                `</svg>` +
                                `<span>${label}</span>` +
                                `</span>`;
                        });
                    }
                }"
                x-init="
                    const base64 = $el.dataset.messageContent;
                    const binary = atob(base64);
                    const bytes = new Uint8Array(binary.length);
                    for (let i = 0; i < binary.length; i++) {
                        bytes[i] = binary.charCodeAt(i);
                    }
                    const content = new TextDecoder('utf-8').decode(bytes);
                    parsedContent = parseFaceContext(content);
                "
                x-html="parsedContent.replace(/\n/g, '<br>')">
            </div>
        </div>
    </article>
@empty
@endforelse
