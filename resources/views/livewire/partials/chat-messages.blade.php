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
                wire:key="message-content-{{ $msg['id'] ?? $loop->index }}"
                x-data="{
                    content: @js($msg['content'] ?? ''),
                    role: @js($msg['role']),
                    isTyping: false,
                    wasTyping: false,
                    parsedContent: '',
                    displayedContent: '',
                    typewriterDone: true,
                    typewriterTimer: null,
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
                    },
                    async typewrite(html) {
                        this.typewriterDone = false;
                        this.displayedContent = '';
                        const plainText = html.replace(/<[^>]*>/g, '');
                        const speed = 15;
                        for (let i = 0; i < plainText.length; i++) {
                            if (this.typewriterTimer === 'cancel') break;
                            await new Promise(r => setTimeout(r, speed));
                            this.displayedContent = html.slice(0, this._findHtmlIndex(html, i + 1));
                        }
                        this.displayedContent = html;
                        this.typewriterDone = true;
                    },
                    _findHtmlIndex(html, charCount) {
                        let chars = 0;
                        let inTag = false;
                        for (let i = 0; i < html.length; i++) {
                            if (html[i] === '<') { inTag = true; continue; }
                            if (html[i] === '>') { inTag = false; continue; }
                            if (!inTag) {
                                chars++;
                                if (chars >= charCount) return i + 1;
                            }
                        }
                        return html.length;
                    },
                    parseContent() {
                        if (this.content === '[TYPING_INDICATOR]') {
                            this.isTyping = true;
                            this.wasTyping = true;
                            this.parsedContent = '';
                            this.displayedContent = '';
                        } else {
                            const wasPreviouslyTyping = this.wasTyping;
                            this.isTyping = false;
                            this.wasTyping = false;
                            let parsed = this.parseFaceContext(this.content);
                            this.parsedContent = this.parseUrls(parsed);

                            if (this.role === 'assistant' && wasPreviouslyTyping) {
                                this.typewrite(this.parsedContent.replace(/\n/g, '<br>'));
                            } else {
                                this.displayedContent = this.parsedContent.replace(/\n/g, '<br>');
                                this.typewriterDone = true;
                            }
                        }
                    }
                }"
                x-init="parseContent()"
                @tolery-assistant-response.window="
                    if (role === 'assistant' && isTyping) {
                        content = $event.detail.content;
                        parseContent();
                    }
                ">
                {{-- Typing indicator --}}
                <div x-show="isTyping" class="typing-indicator">
                    <span></span>
                    <span></span>
                    <span></span>
                </div>

                {{-- Normal message content with typewriter effect for assistant --}}
                <div x-show="!isTyping" x-html="displayedContent"></div>

                {{-- Typewriter cursor for assistant messages --}}
                <span x-show="!isTyping && role === 'assistant' && !typewriterDone"
                      class="inline-block w-0.5 h-4 bg-gray-500 align-middle animate-pulse"></span>
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
