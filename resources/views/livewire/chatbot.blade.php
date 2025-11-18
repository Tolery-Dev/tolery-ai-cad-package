<div class="relative h-screen flex flex-col bg-grey-background">

    {{-- Horizontal Navigation Bar (Figma Design) --}}
    <header class="flex items-center gap-2.5 px-6 pt-8 pb-6 border-b border-grey-stroke dark:border-zinc-800 bg-white dark:bg-zinc-950 shrink-0">
        <button
            onclick="window.location.href='{{ back() }}'"
            class="w-8 h-8 border border-[#CECECE] dark:border-zinc-700 rounded flex items-center justify-center hover:bg-gray-50 dark:hover:bg-zinc-800 transition-colors shrink-0">
            <svg class="w-4 h-4 text-[#323232] dark:text-zinc-300" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M15 18L9 12L15 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
        </button>
        <div
            x-data="{ editing: false, name: @entangle('chat.name').live }"
            class="flex-1">
            <input
                x-show="editing"
                x-model="name"
                @blur="editing = false"
                @keydown.enter="editing = false"
                @keydown.escape="editing = false; name = '{{ $chat->name }}'"
                type="text"
                class="text-sm font-semibold text-black dark:text-white bg-transparent border-none focus:ring-0 focus:outline-none p-0 w-full"
            />
            <span
                x-show="!editing"
                @click="editing = true"
                class="text-sm font-semibold text-black dark:text-white cursor-pointer hover:text-violet-600 dark:hover:text-violet-400 transition-colors">
                <span x-text="name"></span>
            </span>
        </div>
    </header>

    {{-- Main Content Area: Chat (left) + Preview (right) --}}
    <div class="flex-1 flex overflow-hidden">
        {{-- LEFT PANEL: Chat Area (narrower: 400px) --}}
        <section class="w-[35%] shrink-0 flex flex-col bg-white dark:bg-zinc-950 rounded-tl-[24px] rounded-bl-[24px] overflow-hidden">
            {{-- Greeting Header --}}
            <div class="px-6 pt-6 pb-4 shrink-0">
                <flux:heading size="xl" class="flex items-center gap-3">
                    <img src="{{ Vite::asset('resources/images/chat-icon.png')}}" alt="" class="w-10 h-10">
                    <span>Bonjour {{ auth()->user()->firstname }} !</span>
                </flux:heading>
            </div>

            {{-- Messages Area (Scrollable) --}}
            <div id="chat-scroll"
                 x-data="{ scrollToEnd(){ this.$el.scrollTop = this.$el.scrollHeight } }"
                 x-init="$nextTick(()=>scrollToEnd())"
                 x-on:tolery-chat-append.window="scrollToEnd()"
                 class="flex-1 overflow-y-auto px-6 py-6">

                @if(empty($messages))
                    {{-- Welcome Section - Figma Design --}}
                    <div class="flex items-start gap-6">
                        {{-- Content --}}
                        <div class="flex-1 space-y-4">
                            {{-- Welcome Message --}}
                            <div class="space-y-4">
                                <p class="text-base font-normal text-black dark:text-zinc-100">
                                    Bienvenue dans le configurateur de pièces en tôle. Vous pouvez démarrer votre demande de fichier CAO de 3 manières :
                                </p>
                            </div>

                            {{-- 3 Help Blocks --}}
                            <div class="space-y-2">
                                {{-- Block 1: Décrivez votre pièce --}}
                                <div class="bg-white dark:bg-zinc-900 border-[0.5px] border-[#D7DBE0] dark:border-zinc-700 rounded-lg p-4 flex gap-2">
                                    <div class="shrink-0 w-5 h-5 text-black dark:text-zinc-100">
                                        <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                            <path d="M17 3a2.828 2.828 0 114 4L7.5 20.5 2 22l1.5-5.5L17 3z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                        </svg>
                                    </div>
                                    <div class="flex-1 space-y-2">
                                        <p class="text-sm font-medium leading-[1.4] text-black dark:text-zinc-100">
                                            Décrivez votre pièce dans le chat
                                        </p>
                                        <div class="text-xs font-normal leading-[1.4] text-soft-black dark:text-zinc-400 space-y-2">
                                            <p>
                                                Expliquez directement en langage naturel ce que vous souhaitez concevoir, le système transformera votre description en fichier CAO.
                                            </p>
                                            <p>Testez avec ces exemples :</p>
                                        </div>
                                        <div class="flex flex-wrap gap-2">
                                            @php
                                                $buttonColors = [
                                                    'bg-fichiers-10 text-fichiers',
                                                    'bg-devis-10 text-devis',
                                                    'bg-pill-green-bg text-pill-green',
                                                    'bg-pill-orange-bg text-pill-orange',
                                                ];
                                            @endphp
                                            @foreach($predefinedPrompts as $label => $prompt)
                                                @php
                                                    $colorClass = $buttonColors[$loop->index % count($buttonColors)] ?? 'bg-gray-100 text-gray-700';
                                                @endphp
                                                <button wire:click="sendPredefinedPrompt('{{ addslashes($prompt) }}')"
                                                        class="cursor-pointer px-3 py-1 rounded {{ $colorClass }} text-xs font-normal hover:opacity-80 transition-opacity">
                                                    {{ $label }}
                                                </button>
                                            @endforeach
                                        </div>
                                    </div>
                                </div>

                                {{-- Block 2: Importez un plan --}}
                                <div class="relative bg-white dark:bg-zinc-900 border-[0.5px] border-[#D7DBE0] dark:border-zinc-700 rounded-lg p-4 flex gap-2">
                                    <div class="shrink-0 w-5 h-5 text-black dark:text-zinc-100">
                                        <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                            <path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8l-6-6z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                            <path d="M14 2v6h6M16 13H8M16 17H8M10 9H8" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                        </svg>
                                    </div>
                                    <div class="flex-1 space-y-2">
                                        <p class="text-sm font-medium leading-[1.4] text-black dark:text-zinc-100">
                                            Importez un plan ou un pdf technique
                                        </p>
                                        <p class="text-xs font-normal leading-[1.4] text-soft-black dark:text-zinc-400">
                                            Ajoutez un fichier pour que le chat s'appuie sur votre document existant.
                                        </p>
                                        <div class="flex gap-1">
                                            <span class="px-3 py-1 rounded bg-grey-background dark:bg-zinc-800 border-[0.5px] border-grey-stroke dark:border-zinc-700 text-xs font-medium text-soft-black dark:text-zinc-400">
                                                pdf
                                            </span>
                                            <span class="px-3 py-1 rounded bg-grey-background dark:bg-zinc-800 border-[0.5px] border-grey-stroke dark:border-zinc-700 text-xs font-medium text-soft-black dark:text-zinc-400">
                                                png
                                            </span>
                                            <span class="px-3 py-1 rounded bg-grey-background dark:bg-zinc-800 border-[0.5px] border-grey-stroke dark:border-zinc-700 text-xs font-medium text-soft-black dark:text-zinc-400">
                                                jpg
                                            </span>
                                        </div>
                                        {{-- Badge Bientôt disponible --}}
                                        <div class="absolute top-0 right-0 px-3 py-1 rounded-sm bg-fichiers-10 dark:bg-fichiers/10">
                                            <span class="text-xs font-medium text-fichiers dark:text-fichiers">
                                                Bientôt disponible
                                            </span>
                                        </div>
                                    </div>
                                </div>

                                {{-- Block 3: Ajoutez un fichier CAO --}}
                                <div class="relative bg-white dark:bg-zinc-900 border-[0.5px] border-[#D7DBE0] dark:border-zinc-700 rounded-lg p-4 flex gap-2">
                                    <div class="shrink-0 w-5 h-5 text-black dark:text-zinc-100">
                                        <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                            <path d="M20 7h-9M14 17H5M18 12H6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                        </svg>
                                    </div>
                                    <div class="flex-1 space-y-2">
                                        <p class="text-sm font-medium leading-[1.4] text-black dark:text-zinc-100">
                                            Ajoutez un fichier CAO
                                        </p>
                                        <p class="text-xs font-normal leading-[1.4] text-soft-black dark:text-zinc-400">
                                            Chargez un fichier CAO existant pour demander des ajustements ou corrections.
                                        </p>
                                        <div class="flex gap-1">
                                            <span class="px-3 py-1 rounded bg-grey-background dark:bg-zinc-800 border-[0.5px] border-grey-stroke dark:border-zinc-700 text-xs font-medium text-soft-black dark:text-zinc-400">
                                                step
                                            </span>
                                            <span class="px-3 py-1 rounded bg-grey-background dark:bg-zinc-800 border-[0.5px] border-grey-stroke dark:border-zinc-700 text-xs font-medium text-soft-black dark:text-zinc-400">
                                                dxf
                                            </span>
                                        </div>
                                    </div>
                                    {{-- Badge Bientôt disponible --}}
                                    <div class="absolute top-0 right-0 px-3 py-1 rounded-sm bg-fichiers-10 dark:bg-fichiers/10">
                                        <span class="text-xs font-medium text-fichiers dark:text-fichiers">
                                            Bientôt disponible
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                @else
                    {{-- Messages --}}
                    @forelse ($messages ?? [] as $msg)
                        <article class="flex items-start gap-3 mb-4 {{ $msg['role'] === 'user' ? 'flex-row-reverse' : '' }}">
                            <div class="h-8 w-8 shrink-0 rounded-full grid place-items-center {{ $msg['role'] === 'user' ? 'bg-violet-300 text-white' : 'bg-gray-100 dark:bg-zinc-800 text-gray-700 dark:text-zinc-200' }}">
                                @if($msg['role'] === 'user')
                                    👤
                                @else
                                    <img src="{{ Vite::asset('resources/images/chat-icon.png') }}" alt="" class="w-5 h-5">
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
                @endif
            </div>

            {{-- Bottom Input Section --}}
            <footer class="bg-white border-t border-grey-stroke dark:border-zinc-800 shrink-0">
                {{-- New Message Input Area (Figma Design) --}}
                <form wire:submit.prevent="send" class="bg-[#fcfcfc] border-t border-[#ebeff5] px-6 pb-8 pt-6">
                    <div class="flex flex-col gap-2">
                        {{-- Text Input --}}
                        <div class="bg-[#fcfcfc] border border-[#ebeff5] rounded-xl px-4 py-4">
                            <textarea
                                id="message"
                                wire:model.defer="message"
                                rows="1"
                                placeholder="Décrivez votre pièce ou posez une question"
                                x-data="{ resize() { $el.style.height = 'auto'; $el.style.height = $el.scrollHeight + 'px'; } }"
                                x-init="resize()"
                                x-on:input="resize()"
                                x-on:keydown.enter="if (!$event.shiftKey) { $event.preventDefault(); $wire.send() }"
                                class="w-full bg-transparent border-none focus:ring-0 focus:outline-none text-sm font-medium text-black dark:text-zinc-100 placeholder-[#9696b7] resize-none"
                            ></textarea>
                        </div>

                        {{-- Buttons Row --}}
                        <div class="flex items-center justify-between">
                            {{-- Left: Upload Buttons --}}
                            <div class="flex items-center gap-4">

                            </div>

                            {{-- Right: Send Button --}}
                            <button
                                type="submit"
                                class="cursor-pointer w-6 h-6 hover:opacity-80 transition-opacity flex items-center justify-center">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" class="w-6 h-6">
                                    <g clip-path="url(#clip0_2847_31394)">
                                        <path d="M3.4 20.3995L20.85 12.9195C21.66 12.5695 21.66 11.4295 20.85 11.0795L3.4 3.59953C2.74 3.30953 2.01 3.79953 2.01 4.50953L2 9.11953C2 9.61953 2.37 10.0495 2.87 10.1095L17 11.9995L2.87 13.8795C2.37 13.9495 2 14.3795 2 14.8795L2.01 19.4895C2.01 20.1995 2.74 20.6895 3.4 20.3995Z" fill="#565C66"/>
                                    </g>
                                    <defs>
                                        <clipPath id="clip0_2847_31394">
                                            <rect width="24" height="24" fill="white"/>
                                        </clipPath>
                                    </defs>
                                </svg>
                            </button>
                        </div>
                    </div>
                </form>
            </footer>
        </section>

        {{-- RIGHT PANEL: Preview/Status Area --}}
        <section class="flex-1 bg-grey-background dark:bg-zinc-900 p-8">
            <div class="relative h-full w-full bg-white dark:bg-zinc-950 border border-grey-stroke dark:border-zinc-800 rounded overflow-hidden">
                {{-- Modal progression CAD --}}
                <div x-data="cadStreamModal()"
                     x-show="open"
                     x-cloak
                     class="absolute inset-0 z-50 flex items-start justify-center pt-8 bg-white dark:bg-zinc-950">
                    <div class="w-full max-w-4xl mx-4">
                        <div class="bg-gradient-to-r from-violet-600 to-indigo-800 px-6 py-4 text-white flex items-center justify-between rounded-t-2xl">
                            <h3 class="text-lg font-semibold">Processing</h3>
                            <div class="text-sm" x-text="`${completedSteps} out of 5 steps completed`"></div>
                        </div>

                        <div class="bg-white dark:bg-zinc-900 p-6 rounded-b-2xl shadow-2xl">
                            <div class="flex items-center justify-between gap-6 mb-6">
                                <template x-for="s in steps" :key="s.key">
                                    <div class="flex flex-col items-center gap-2 flex-1">
                                        <div class="relative">
                                            <span class="h-12 w-12 rounded-full grid place-items-center text-lg font-semibold transition-all duration-300"
                                                  :class="s.state==='done' ? 'bg-violet-600 text-white scale-100' : (s.state==='active' ? 'bg-violet-100 text-violet-600 border-2 border-violet-500 animate-pulse scale-110' : 'bg-gray-100 text-gray-400 scale-90')">
                                                <span x-show="s.state === 'done'">✓</span>
                                                <span x-show="s.state !== 'done'"
                                                      class="inline-block"
                                                      :class="s.state === 'active' ? 'animate-spin' : ''"
                                                      x-html="s.state === 'active' ? '●' : '○'"></span>
                                            </span>
                                            <svg x-show="s.state === 'active'" class="absolute inset-0 w-12 h-12 -rotate-90" viewBox="0 0 48 48">
                                                <circle cx="24" cy="24" r="22" fill="none" stroke="#a78bfa" stroke-width="2"
                                                        stroke-dasharray="138" stroke-dashoffset="69"
                                                        class="animate-spin origin-center">
                                                </circle>
                                            </svg>
                                        </div>
                                        <span class="text-xs font-medium text-center"
                                              :class="s.state === 'inactive' ? 'text-gray-400' : 'text-gray-900 dark:text-zinc-100'"
                                              x-text="s.label">
                                        </span>
                                    </div>
                                </template>
                            </div>

                            <div class="w-full h-2 bg-gray-100 dark:bg-zinc-800 rounded-full overflow-hidden">
                                <div class="h-2 bg-gradient-to-r from-violet-600 to-indigo-600 transition-all duration-500 ease-out"
                                     :style="`width: ${overall}%`"></div>
                            </div>

                            <div class="mt-4 text-sm text-gray-600 dark:text-zinc-300 flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    <span class="inline-block h-2 w-2 rounded-full animate-pulse"
                                        :class="activeStep ? 'bg-violet-500' : 'bg-gray-300'"></span>
                                    <span x-text="statusText"></span>
                                </div>
                                <div class="font-semibold" x-text="`${overall}%`"></div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- 3D Viewer --}}
                <div id="viewer"
                     wire:ignore
                     data-screenshot-exists="{{ $screenshotUrl ? 'true' : 'false' }}"
                     class="h-full w-full">
                </div>

                {{-- Config Panel --}}
                @include('ai-cad::partials.cad-config-panel', [
                    'stepExportUrl' => $stepExportUrl,
                    'objExportUrl' => $objExportUrl,
                    'technicalDrawingUrl' => $technicalDrawingUrl,
                    'screenshotUrl' => $screenshotUrl
                ])

                {{-- Empty State --}}
                @if(!$objExportUrl && !$stepExportUrl)
                    <div class="absolute inset-0 flex flex-col items-center justify-center pointer-events-none p-8">
                        <div class="w-60 h-60 rounded-full bg-gradient-to-br from-violet-100 to-indigo-100 dark:from-violet-900/20 dark:to-indigo-900/20 flex items-center justify-center mb-8">
                            <svg class="w-32 h-32 text-violet-300 dark:text-violet-700" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M12 2L2 7L12 12L22 7L12 2Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                <path d="M2 17L12 22L22 17" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                <path d="M2 12L12 17L22 12" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </div>
                        <p class="text-sm font-medium text-black dark:text-zinc-100 text-center max-w-[310px]">
                            Votre pièce apparaîtra ici dès qu'elle sera prête.
                        </p>
                    </div>
                @endif

                {{-- Bouton de téléchargement - CTA Principal (Bottom Right) --}}
                @if($stepExportUrl || $objExportUrl)
                    <div class="absolute bottom-8 right-8 z-10">
                        <flux:button
                            wire:click="initiateDownload"
                            variant="primary"
                            icon="arrow-down-tray"
                            class="cursor-pointer !bg-violet-600 hover:!bg-violet-700 !text-white shadow-lg !px-6 !py-3 !text-base !font-semibold">
                            Télécharger les fichiers
                        </flux:button>
                    </div>
                @endif
            </div>
        </section>
    </div>

    {{-- Stripe Payment Modal Component --}}
    <livewire:stripe-payment-modal />

    {{-- Modal Achat/Abonnement --}}
    <flux:modal name="purchase-or-subscribe" :open="$showPurchaseModal" wire:model="showPurchaseModal" class="space-y-6 min-w-[32rem]">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg" class="mb-2">Débloquer ce fichier CAO</flux:heading>
                <flux:subheading>
                    @if($downloadStatus && $downloadStatus['reason'] === 'no_subscription')
                        Vous devez être abonné ou acheter ce fichier pour le télécharger.
                    @elseif($downloadStatus && $downloadStatus['reason'] === 'quota_exceeded')
                        Votre quota mensuel est épuisé ({{ $downloadStatus['total_quota'] }}/{{ $downloadStatus['total_quota'] }} fichiers).
                    @else
                        Vous n'avez pas accès à ce téléchargement.
                    @endif
                </flux:subheading>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                {{-- Option 1: S'abonner --}}
                @if($downloadStatus && isset($downloadStatus['options']['can_subscribe']) && $downloadStatus['options']['can_subscribe'])
                    <flux:card class="border-2 border-violet-200 dark:border-violet-800">
                        <div class="flex flex-col h-full">
                            <div class="flex-1">
                                <flux:heading size="base" class="mb-2 text-violet-600 dark:text-violet-400">
                                    S'abonner
                                </flux:heading>
                                <flux:subheading class="mb-4">
                                    Accès illimité aux téléchargements selon votre plan
                                </flux:subheading>
                                <ul class="space-y-2 text-sm text-zinc-600 dark:text-zinc-400">
                                    <li class="flex items-start gap-2">
                                        <flux:icon.check class="size-4 text-green-600 shrink-0 mt-0.5" />
                                        <span>Plusieurs fichiers par mois</span>
                                    </li>
                                    <li class="flex items-start gap-2">
                                        <flux:icon.check class="size-4 text-green-600 shrink-0 mt-0.5" />
                                        <span>Accès prioritaire au support</span>
                                    </li>
                                    <li class="flex items-start gap-2">
                                        <flux:icon.check class="size-4 text-green-600 shrink-0 mt-0.5" />
                                        <span>Nouvelles fonctionnalités en avant-première</span>
                                    </li>
                                </ul>
                            </div>
                            <flux:button
                                wire:click="redirectToSubscription"
                                variant="primary"
                                class="mt-4 w-full !bg-violet-600 hover:!bg-violet-700">
                                Voir les plans
                            </flux:button>
                        </div>
                    </flux:card>
                @endif

                {{-- Option 2: Achat one-shot --}}
                @if($downloadStatus && isset($downloadStatus['options']['can_purchase']) && $downloadStatus['options']['can_purchase'])
                    <flux:card class="border-2 border-zinc-200 dark:border-zinc-700">
                        <div class="flex flex-col h-full">
                            <div class="flex-1">
                                <flux:heading size="base" class="mb-2">
                                    Acheter ce fichier
                                </flux:heading>
                                <flux:subheading class="mb-4">
                                    Paiement unique pour ce fichier uniquement
                                </flux:subheading>
                                @if(isset($downloadStatus['options']['purchase_price']))
                                    <div class="text-3xl font-bold text-zinc-900 dark:text-zinc-100 mb-2">
                                        {{ number_format($downloadStatus['options']['purchase_price'] / 100, 2) }}€
                                    </div>
                                @endif
                                <ul class="space-y-2 text-sm text-zinc-600 dark:text-zinc-400">
                                    <li class="flex items-start gap-2">
                                        <flux:icon.check class="size-4 text-green-600 shrink-0 mt-0.5" />
                                        <span>Téléchargement immédiat</span>
                                    </li>
                                    <li class="flex items-start gap-2">
                                        <flux:icon.check class="size-4 text-green-600 shrink-0 mt-0.5" />
                                        <span>Fichier STEP haute qualité</span>
                                    </li>
                                    <li class="flex items-start gap-2">
                                        <flux:icon.check class="size-4 text-green-600 shrink-0 mt-0.5" />
                                        <span>Accès illimité à ce fichier</span>
                                    </li>
                                </ul>
                            </div>
                            <flux:button
                                wire:click="purchaseFile"
                                variant="outline"
                                class="mt-4 w-full">
                                Acheter maintenant
                            </flux:button>
                        </div>
                    </flux:card>
                @endif
            </div>
        </div>

        <div class="flex gap-2 justify-end pt-6 border-t border-zinc-200 dark:border-zinc-700">
            <flux:modal.close>
                <flux:button variant="ghost">
                    Annuler
                </flux:button>
            </flux:modal.close>
        </div>
    </flux:modal>
</div>

@script
<script>
    Alpine.data('cadStreamModal', () => {
        return {
            open: false,
            cancelable: false,
            controller: null,
            overall: 0,
            statusText: 'Initialisation...',
            activeStep: null,
            completedSteps: 0,
            steps: [
                {key: 'analysis', label: 'Analysis', state: 'inactive'},
                {key: 'parameters', label: 'Parameters', state: 'inactive'},
                {key: 'generation_code', label: 'Generation', state: 'inactive'},
                {key: 'export', label: 'Export', state: 'inactive'},
                {key: 'complete', label: 'Complete', state: 'inactive'},
            ],
            init() {
                const comp = this;
                this._onLivewire = ({message, sessionId, isEdit = false}) => comp.startStream(message, sessionId, isEdit);
                Livewire.on('aicad:startStream', this._onLivewire);
                Livewire.on('aicad-start-stream', this._onLivewire);

                // Listen for cached stream event
                this._onCached = ({cachedData, simulationDuration}) => comp.simulateCachedStream(cachedData, simulationDuration);
                Livewire.on('aicad-start-cached-stream', this._onCached);

                const input = document.querySelector('#message');
                Livewire.on('tolery-chat-focus-input', () => {
                    if (input) {
                        input.focus();
                        input.setSelectionRange(input.value.length, input.value.length);
                    }
                });
            },
            reset() {
                this.overall = 0;
                this.statusText = 'Initializing…';
                this.activeStep = null;
                this.completedSteps = 0;
                this.steps.forEach(s => s.state = 'inactive');
            },
            markStep(stepKey, status, message, pct) {
                const idx = this.steps.findIndex(s => s.key === stepKey);
                if (idx >= 0) {
                    for (let i = 0; i < idx; i++) {
                        if (this.steps[i].state !== 'done') this.steps[i].state = 'done';
                    }
                    this.steps[idx].state = status?.toLowerCase().includes('completed') ? 'done' : 'active';
                    this.activeStep = stepKey;
                    this.completedSteps = this.steps.filter(s => s.state === 'done').length;
                }
                if (typeof pct === 'number') {
                    this.overall = Math.max(0, Math.min(100, pct));
                }
                this.statusText = message || status || 'Processing…';
            },
            async simulateCachedStream(cachedData, duration = 10000) {
                // Simulate the 5-step stream with cached data
                this.reset();
                this.open = true;
                this.cancelable = false; // Don't allow cancel during simulation

                const stepDuration = duration / 5; // 2 seconds per step at 10s total
                const simulatedSteps = cachedData.simulated_steps || {};

                // Step 1: Analysis
                await this.animateStep('analysis', simulatedSteps.analysis || ['Analyse en cours...'], stepDuration, 20);

                // Step 2: Parameters
                await this.animateStep('parameters', simulatedSteps.parameters || ['Calcul des paramètres...'], stepDuration, 40);

                // Step 3: Generation
                await this.animateStep('generation_code', simulatedSteps.generation_code || ['Génération du code...'], stepDuration, 60);

                // Step 4: Export
                await this.animateStep('export', simulatedSteps.export || ['Export des fichiers...'], stepDuration, 80);

                // Step 5: Complete
                await this.animateStep('complete', simulatedSteps.complete || ['Finalisation...'], stepDuration / 2, 95);

                // Mark as complete
                this.markStep('complete', 'Completed', cachedData.chat_response || 'Pièce prête !', 100);

                // Save the cached data to backend
                $wire.saveCachedFinal(cachedData);

                // Close modal after brief delay
                this.cancelable = true;
                setTimeout(() => this.close(), 800);
            },
            async animateStep(stepKey, messages, duration, targetPercentage) {
                const messageCount = messages.length;
                const messageDuration = duration / messageCount;

                for (let i = 0; i < messageCount; i++) {
                    const message = messages[i];
                    const progress = targetPercentage - ((messageCount - i - 1) * 5); // Gradual increase

                    this.markStep(stepKey, i === messageCount - 1 ? 'completed' : 'active', message, progress);

                    // Add small random variation for natural feel
                    const jitter = Math.random() * 400 - 200; // ±200ms
                    await new Promise(resolve => setTimeout(resolve, messageDuration + jitter));
                }
            },
            async startStream(message, sessionId, isEdit = false) {
                this.reset();
                this.open = true;
                this.cancelable = true;
                this.controller = new AbortController();

                const url = @js(route('ai-cad.stream.generate-cad'));

                try {
                    const res = await fetch(url, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'text/event-stream',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                        },
                        body: JSON.stringify({
                            message: String(message ?? ''),
                            session_id: String(sessionId ?? ''),
                            is_edit_request: isEdit,
                        }),
                        signal: this.controller.signal,
                    });
                    if (!res.ok || !res.body) {
                        throw new Error(`Stream error: ${res.status}`);
                    }
                    const reader = res.body.getReader();
                    const decoder = new TextDecoder();
                    let buffer = '';

                    while (true) {
                        const {value, done} = await reader.read();
                        if (done) break;
                        buffer += decoder.decode(value, {stream: true});

                        let sep;
                        while ((sep = buffer.indexOf('\n\n')) !== -1) {
                            const packet = buffer.slice(0, sep);
                            buffer = buffer.slice(sep + 2);

                            const lines = packet.split('\n').map(l => l.trim());
                            for (const line of lines) {
                                if (!line || line.startsWith(':')) continue;
                                if (!line.startsWith('data:')) continue;

                                const json = line.slice(5).trim();
                                if (!json || json === '[DONE]') continue;

                                let payload;
                                try {
                                    payload = JSON.parse(json);
                                } catch {
                                    continue;
                                }

                                if (payload.final_response) {
                                    const resp = payload.final_response || {};
                                    $wire.saveStreamFinal(resp)
                                    this.markStep('complete', 'Completed', resp.chat_response || 'Completed', 100);
                                    $wire.refreshFromDb();
                                    this.cancelable = true;
                                    setTimeout(() => this.close(), 800);
                                    continue;
                                }

                                const step = payload.step || null;
                                const status = payload.status || '';
                                const msg = payload.message || '';
                                const pct = typeof payload.overall_percentage === 'number' ? payload.overall_percentage : null;
                                if (step) this.markStep(step, status, msg, pct ?? this.overall);
                            }
                        }
                    }
                } catch (e) {
                    console.error(e);
                    this.statusText = 'Stream connection error. Retrying soon…';
                    this.cancelable = true;
                }
            },
            close() {
                try {
                    this.controller?.abort();
                } catch {}
                this.open = false;
            }
        }
    });


</script>
@endscript
