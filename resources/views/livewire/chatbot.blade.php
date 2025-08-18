@php
    // Ajuste ici si ton header fait autre chose que 96px
    $HEADER_H = 120; // en px
@endphp

<div class="mx-auto w-full max-w-[1600px] px-4 lg:px-6">

    {{-- Grille pleine hauteur de fenêtre (moins le header) --}}
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-4 lg:gap-6 overflow-y-auto"
         style="height: calc(100vh - {{ $HEADER_H }}px);">

        {{-- GAUCHE (1/3) — Chat sticky full height --}}
        <section class="lg:col-span-4 h-full">
            <div class="sticky"
                 style="top: 16px; height: calc(100vh - {{ $HEADER_H }}px - 32px);">
                <div
                    class="h-full dark:bg-zinc-900 flex flex-col overflow-hidden">
                    {{-- Header chat --}}
                    <header
                        class="px-4 py-3 border-b border-gray-100 dark:border-zinc-800 flex items-center justify-between">
                        <h2 class="text-sm font-semibold text-gray-900 dark:text-zinc-100">Tolery • Assistant CAO</h2>
                        @if($isProcessing ?? false)
                            <span class="text-xs text-blue-600">Calcul…</span>
                        @endif
                    </header>

                    {{-- Messages (scroll) --}}
                    <div id="chat-scroll"
                         x-data="{ scrollToEnd(){ this.$el.scrollTop = this.$el.scrollHeight } }"
                         x-init="$nextTick(()=>scrollToEnd())"
                         x-on:tolery-chat:append.window="scrollToEnd()"
                         class="flex-1 overflow-y-auto px-4 py-4 space-y-4">
                        @forelse ($messages ?? [] as $msg)
                            <article class="flex items-start gap-3 {{ $msg['role'] === 'user' ? 'flex-row-reverse' : '' }}">
                                <div class="h-8 w-8 shrink-0 rounded-full grid place-items-center
                            {{ $msg['role'] === 'user' ? 'bg-violet-300 text-white' : 'bg-gray-100 dark:bg-zinc-800 text-gray-700 dark:text-zinc-200' }}">
                                    {{ $msg['role'] === 'user' ? '👤' : '🤖' }}
                                </div>
                                <div class="flex-1 {{ $msg['role'] === 'user' ? 'text-right' : '' }}">
                                    <div class="text-xs text-gray-500 dark:text-zinc-400 mb-1">
                                        {{ $msg['role'] === 'user' ? 'Vous' : 'Tolery' }}
                                        <span class="mx-1">•</span>
                                        <time>{{ \Illuminate\Support\Carbon::parse($msg['created_at'] ?? now())->format('H:i') }}</time>
                                    </div>
                                    <div class="{{ $msg['role'] === 'user' ? 'inline-block border border-gray-100 bg-gray-50' : 'inline-block bg-gray-100 dark:bg-zinc-800 text-gray-900 dark:text-zinc-100' }} rounded-xl px-3 py-2">
                                        {!! nl2br(e($msg['content'] ?? '')) !!}
                                    </div>
                                </div>
                            </article>
                        @empty
                            <div class="text-sm text-gray-500 dark:text-zinc-400">Démarrez une conversation avec votre
                                demande de pièce.
                            </div>
                        @endforelse
                    </div>

                    {{-- Composer --}}
                    <footer class="border-t border-gray-100 dark:border-zinc-800 p-3">
                        <form wire:submit.prevent="send" class="flex flex-col gap-2">
                            <div>
                                <label for="message" class="sr-only">Votre message</label>
                                <flux:textarea
                                    id="message"
                                    rows="2"
                                    placeholder="Décrivez votre pièce ou posez une question…"
                                    wire:model.defer="message"
                                    class="rounded-xl transition-all duration-200
                                           border border-violet-500/20 ring-1 ring-violet-500/20
                                           shadow-md shadow-violet-500/10
                                           focus:ring-2 focus:ring-violet-500/50
                                           focus:shadow-lg focus:shadow-violet-500/20
                                           focus:border-violet-500/50"
                                />
                            </div>
                            <div class="flex justify-end">
                                <flux:button type="submit" variant="ghost" icon="paper-airplane" />
                            </div>
                        </form>
                    </footer>
                </div>
            </div>
        </section>

        {{-- DROITE (2/3) — Viewer plein espace + fond gris + panneau volant --}}
        <section class="lg:col-span-8 h-full">
            {{-- Fond gris autour du viewer (comme ta maquette) --}}
            <div class="h-full bg-gray-100 p-4">
                <div
                    class="relative h-full rounded-xl border border-gray-200 dark:border-zinc-800 bg-white overflow-hidden shadow-sm">
                    {{-- Le canvas/WebGL prend 100% de la carte blanche --}}
                    <div id="viewer" class="absolute inset-0 bg-white dark:bg-zinc-900"></div>
                </div>
            </div>
        </section>

        {{-- Fenêtre volante (drag + toggle, contour/ombre violets) --}}
        @include('ai-cad::partials.cad-config-panel')
    </div>
</div>
