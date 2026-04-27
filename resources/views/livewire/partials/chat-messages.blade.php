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
                class="{{ $msg['role'] === 'user' ? 'inline-block bg-violet-100 text-gray-900 text-left' : 'inline-block bg-gray-100 text-gray-900' }} rounded-xl px-3 py-2"
                wire:key="msg-{{ $msg['id'] ?? $loop->index }}"
                x-data="chatMessage({{ Js::from($msg['content'] ?? '') }}, {{ Js::from($msg['role']) }}, {{ Js::from($dfmErrorCodes ?? []) }})"
                x-init="parseContent(); $nextTick(() => highlightCode())">
                {{-- Typing indicator --}}
                <div x-show="isTyping" class="typing-indicator">
                    <span></span>
                    <span></span>
                    <span></span>
                </div>

                {{-- DFM Error Code display --}}
                <div x-show="isErrorCode" x-cloak class="flex items-start gap-2 text-amber-800">
                    <svg class="w-5 h-5 shrink-0 text-amber-500 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4.5c-.77-.833-2.694-.833-3.464 0L3.34 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
                    </svg>
                    <div>
                        <span class="text-xs font-mono text-amber-500" x-text="'Code ' + content.trim()"></span>
                        <p class="text-sm mt-0.5" x-text="errorMessage"></p>
                    </div>
                </div>

                {{-- Normal message content --}}
                <div x-show="!isTyping && !isErrorCode" x-html="displayedContent"
                     :class="role === 'assistant' ? 'prose prose-sm prose-gray max-w-none prose-p:my-1 prose-ul:my-1 prose-ol:my-1 prose-li:my-0.5 prose-headings:my-2 prose-pre:my-2 prose-code:text-violet-700 prose-code:bg-violet-50 prose-code:px-1 prose-code:py-0.5 prose-code:rounded prose-a:text-violet-600 hover:prose-a:text-violet-800' : ''">
                </div>

            </div>

        </div>
    </article>
@empty
@endforelse

@once
@script
<script>
    Alpine.data('chatMessage', (content, role, dfmErrorCodes) => ({
        content: content,
        role: role,
        dfmErrorCodes: dfmErrorCodes,
        isTyping: false,
        isErrorCode: false,
        errorMessage: '',
        displayedContent: '',

        checkDfmErrorCode(text) {
            if (this.role !== 'assistant' || !text) return false;
            var trimmed = text.trim();
            if (this.dfmErrorCodes[trimmed]) {
                this.isErrorCode = true;
                this.errorMessage = this.dfmErrorCodes[trimmed];
                return true;
            }
            return false;
        },

        parseUrls(text) {
            if (!text) return text;
            var urlRegex = /(https?:\/\/[^\s<]+)/g;
            return text.replace(urlRegex, function(url) {
                var cleanUrl = url.replace(/[.,;:!?)]+$/, '');
                var trailing = url.slice(cleanUrl.length);
                return '<a href="' + cleanUrl + '" target="_blank" rel="noopener noreferrer" class="text-violet-600 hover:text-violet-800 underline">' + cleanUrl + '</a>' + trailing;
            });
        },

        parseFaceContext(text) {
            if (!text) return text;
            // Parse face contexts — chip shows only Face ID, full context goes to AI
            var result = text.replace(/\[FACE_CONTEXT:\s*(.+?)\]\]/g, function(match, ctx) {
                var idMatch = ctx.match(/ID\[([^\]]+)\]/);
                var faceId = idMatch ? idMatch[1] : 'Unknown';
                return '<span class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full border border-violet-200 bg-violet-50 text-violet-700 text-sm font-medium">' +
                    '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">' +
                    '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>' +
                    '</svg>' +
                    '<span>Face ' + faceId + '</span>' +
                    '</span>';
            });
            // Compact `[Face ID: XXX]` markers (sent by the CAD config panel on regen requests)
            result = result.replace(/\s*\[Face ID:\s*([^\]]+)\]/g, function(match, faceId) {
                return ' <span class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full border border-violet-200 bg-violet-50 text-violet-700 text-sm font-medium">' +
                    '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">' +
                    '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>' +
                    '</svg>' +
                    '<span>Face ' + faceId.trim() + '</span>' +
                    '</span>';
            });
            // Parse edge contexts
            result = result.replace(/\[EDGE_CONTEXT:\s*(.+?)\]\]/g, function(match, ctx) {
                var idMatch = ctx.match(/ID\[([^\]]+)\]/);
                var edgeId = idMatch ? idMatch[1] : 'Unknown';
                var lenMatch = ctx.match(/Length\[([^\]]+)\]/);
                var lenStr = lenMatch ? ' (' + lenMatch[1] + ' mm)' : '';
                return '<span class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full border border-green-200 bg-green-50 text-green-700 text-sm font-medium">' +
                    '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">' +
                    '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 12h16"></path>' +
                    '</svg>' +
                    '<span>Edge ' + edgeId + lenStr + '</span>' +
                    '</span>';
            });
            return result;
        },

        parseMarkdown(text) {
            if (!text || typeof window.marked === 'undefined') {
                return text ? text.replace(/\n/g, '<br>') : text;
            }
            var html = marked.parse(text, { breaks: true, gfm: true });
            // Propagate span color to child strong/em so prose classes do not override
            var div = document.createElement('div');
            div.innerHTML = html;
            div.querySelectorAll('span[style]').forEach(function(span) {
                var color = span.style.color;
                if (color) {
                    span.querySelectorAll('strong, em, b, i').forEach(function(child) {
                        child.style.color = color;
                    });
                }
            });
            return div.innerHTML;
        },

        highlightCode() {
            if (typeof window.hljs !== 'undefined') {
                this.$el.querySelectorAll('pre code').forEach(function(block) {
                    hljs.highlightElement(block);
                });
            }
        },

        parseContent() {
            if (this.content === '[TYPING_INDICATOR]') {
                this.isTyping = true;
                this.displayedContent = '';
            } else if (this.checkDfmErrorCode(this.content)) {
                this.isTyping = false;
            } else {
                this.isTyping = false;
                var parsed = this.parseFaceContext(this.content);
                this.displayedContent = this.role === 'assistant'
                    ? this.parseMarkdown(parsed)
                    : this.parseUrls(parsed).replace(/\n/g, '<br>');
            }
        }
    }));
</script>
@endscript
@endonce
