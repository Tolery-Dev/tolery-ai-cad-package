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
                x-data="{
                    content: @js($msg['content'] ?? ''),
                    role: @js($msg['role']),
                    dfmErrorCodes: @js($dfmErrorCodes ?? []),
                    isTyping: false,
                    isErrorCode: false,
                    errorMessage: '',
                    displayedContent: '',
                    checkDfmErrorCode(text) {
                        if (this.role !== 'assistant' || !text) return false;
                        const trimmed = text.trim();
                        if (this.dfmErrorCodes[trimmed]) {
                            this.isErrorCode = true;
                            this.errorMessage = this.dfmErrorCodes[trimmed];
                            return true;
                        }
                        return false;
                    },
                    parseUrls(text) {
                        if (!text) return text;
                        const urlRegex = /(https?:\/\/[^\s<]+)/g;
                        return text.replace(urlRegex, (url) => {
                            const cleanUrl = url.replace(/[.,;:!?)]+$/, '');
                            const trailing = url.slice(cleanUrl.length);
                            return `<a href='${cleanUrl}' target='_blank' rel='noopener noreferrer' class='text-violet-600 hover:text-violet-800 underline'>${cleanUrl}</a>${trailing}`;
                        });
                    },
                    parseFaceContext(text) {
                        if (!text) return text;
                        return text.replace(/\[FACE_CONTEXT:\s*(.+?)\]\]/g, (match, faceContext) => {
                            const idMatch = faceContext.match(/ID\[([^\]]+)\]/);
                            const faceId = idMatch ? idMatch[1] : 'Unknown';
                            const label = 'Face ' + faceId;
                            return `<span class='inline-flex items-center gap-2 px-3 py-1.5 rounded-full border border-violet-200 bg-violet-50 text-violet-700 text-sm font-medium'>` +
                                `<svg class='w-4 h-4' fill='none' stroke='currentColor' viewBox='0 0 24 24'>` +
                                `<path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4'></path>` +
                                `</svg>` +
                                `<span>${label}</span>` +
                                `</span>`;
                        });
                    },
                    parseMarkdown(text) {
                        if (!text || typeof window.marked === 'undefined') {
                            return text ? text.replace(/\n/g, '<br>') : text;
                        }
                        return marked.parse(text, { breaks: true });
                    },
                    parseContent() {
                        if (this.content === '[TYPING_INDICATOR]') {
                            this.isTyping = true;
                            this.displayedContent = '';
                        } else if (this.checkDfmErrorCode(this.content)) {
                            this.isTyping = false;
                        } else {
                            this.isTyping = false;
                            let parsed = this.parseFaceContext(this.content);
                            this.displayedContent = this.role === 'assistant'
                                ? this.parseMarkdown(parsed)
                                : this.parseUrls(parsed).replace(/\n/g, '<br>');
                        }
                    }
                }"
                x-init="parseContent()">
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

                {{-- Normal message content with typewriter effect for assistant --}}
                <div x-show="!isTyping && !isErrorCode" x-html="displayedContent"
                     :class="role === 'assistant' ? 'prose prose-sm prose-gray max-w-none prose-p:my-1 prose-ul:my-1 prose-ol:my-1 prose-li:my-0.5 prose-headings:my-2 prose-pre:my-2 prose-code:text-violet-700 prose-code:bg-violet-50 prose-code:px-1 prose-code:py-0.5 prose-code:rounded prose-a:text-violet-600 hover:prose-a:text-violet-800' : ''"></div>

            </div>

            {{-- Suggestions contextuelles (dernier message assistant uniquement) --}}
            @if($msg['role'] === 'assistant' && $loop->last && ($msg['content'] ?? '') !== '[TYPING_INDICATOR]' && !empty($contextualSuggestions))
                <div class="flex flex-wrap gap-2 mt-3" x-data x-show="$el.closest('article').dataset.isLast === 'true'">
                    @foreach($contextualSuggestions as $suggestion)
                        <button wire:click="sendPredefinedPrompt('{{ addslashes($suggestion['prompt']) }}')"
                                class="cursor-pointer px-3 py-1.5 rounded-full border border-violet-200 bg-violet-50 text-violet-700 text-xs font-medium hover:bg-violet-100 hover:border-violet-300 transition-all duration-200">
                            {{ $suggestion['label'] }}
                        </button>
                    @endforeach
                </div>
            @endif
        </div>
    </article>
@empty
@endforelse
