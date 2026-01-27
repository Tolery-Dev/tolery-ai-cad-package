<section class="flex-1 bg-grey-background p-4">
    <div class="relative h-full w-full bg-white border border-grey-stroke rounded-xl overflow-hidden">
        <div x-data="cadStreamModal()"
             x-show="open"
             x-cloak
             class="absolute inset-0 z-50 flex items-start justify-center pt-8 bg-white">
            <div class="w-full max-w-4xl mx-4">
                <div class="bg-gradient-to-r from-violet-600 to-indigo-800 px-6 py-4 text-white flex items-center justify-between rounded-t-2xl">
                    <h3 class="text-lg font-semibold">Création de votre fichier CAO en cours</h3>
                    <div class="text-sm" x-text="`${completedSteps} sur 5 étapes terminées`"></div>
                </div>

                <div class="bg-white p-6 rounded-b-2xl shadow-2xl">
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
                                      :class="s.state === 'inactive' ? 'text-gray-400' : 'text-gray-900'"
                                      x-text="s.shortLabel">
                                </span>
                            </div>
                        </template>
                    </div>

                    <div class="w-full h-2 bg-gray-100 rounded-full overflow-hidden">
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

                    {{-- Error state with retry button --}}
                    <template x-if="hasError">
                        <div class="mt-6 p-4 bg-red-50 border border-red-200 rounded-lg">
                            <div class="flex items-start gap-3">
                                <svg class="w-5 h-5 text-red-600 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                                <div class="flex-1">
                                    <p class="text-sm font-medium text-red-900 mb-1" x-text="errorMessage || 'Une erreur est survenue'"></p>

                                    {{-- Team notified message --}}
                                    <template x-if="teamNotified">
                                        <div class="mt-2 p-2 bg-blue-50 border border-blue-200 rounded text-xs text-blue-800">
                                            <p class="font-medium">L'équipe Tolery a été automatiquement notifiée.</p>
                                            <p class="mt-1">Nous analysons le problème et reviendrons vers vous rapidement.</p>
                                        </div>
                                    </template>

                                    {{-- Retry button --}}
                                    <template x-if="!teamNotified">
                                        <button
                                            @click="manualRetry()"
                                            class="mt-3 inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-white bg-violet-600 hover:bg-violet-700 rounded-lg transition-colors">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                                            </svg>
                                            Réessayer
                                        </button>
                                    </template>

                                    {{-- Close button when team is notified --}}
                                    <template x-if="teamNotified">
                                        <button
                                            @click="close()"
                                            class="mt-3 inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 hover:bg-gray-200 rounded-lg transition-colors">
                                            Fermer
                                        </button>
                                    </template>
                                </div>
                            </div>
                        </div>
                    </template>

                    {{-- Avertissement (only when not in error state) --}}
                    <template x-if="!hasError">
                        <div class="mt-6 p-4 bg-amber-50 border border-amber-200 rounded-lg flex items-start gap-3">
                            <svg class="w-5 h-5 text-amber-600 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                            </svg>
                            <div class="flex-1">
                                <p class="text-sm font-medium text-amber-900 mb-1">Ne fermez pas cette fenêtre</p>
                                <p class="text-xs text-amber-700">La génération de votre pièce est en cours. Fermer cette fenêtre interrompra le processus.</p>
                            </div>
                        </div>
                    </template>
                </div>
            </div>
        </div>

        <div id="viewer"
             wire:ignore
             data-screenshot-exists="{{ $screenshotUrl ? 'true' : 'false' }}"
             class="relative h-full w-full">
        </div>

        {{-- wire:ignore prevents Livewire from re-rendering the Alpine component after piece generation --}}
        {{-- This fixes the issue where selection buttons stop working after dynamic updates --}}
        <div wire:ignore>
            @include('ai-cad::partials.cad-config-panel', [
                'stepExportUrl' => $stepExportUrl,
                'objExportUrl' => $objExportUrl,
                'technicalDrawingUrl' => $technicalDrawingUrl,
                'screenshotUrl' => $screenshotUrl
            ])
        </div>

        @if(!$objExportUrl && !$stepExportUrl)
            <div class="absolute inset-0 flex flex-col items-center justify-center pointer-events-none p-8">
                <div class="w-80 h-auto flex items-center justify-center mb-8">
                    <img src="{{ asset('vendor/ai-cad/images/tolery-large-logo.svg') }}" alt="ToleryCAD" />
                </div>
                <p class="text-sm font-medium text-black text-center max-w-[310px]">
                    Votre pièce apparaîtra ici dès qu'elle sera générée.
                </p>
            </div>
        @endif

        @if($stepExportUrl || $objExportUrl)
            <div class="absolute bottom-6 left-1/2 -translate-x-1/2 z-10 flex flex-col items-center gap-2">
                @if ($currentVersion = $this->getCurrentVersionLabel())
                    <div class="flex items-center gap-2 px-3 py-1.5 text-sm font-medium text-purple-700 bg-white/90 backdrop-blur-sm rounded-full shadow-md border border-purple-200">
                        <flux:icon.cube-transparent class="size-4" />
                        <span>{{ $currentVersion }}</span>
                    </div>
                @endif

                <flux:button
                    wire:click="initiateDownload"
                    variant="primary"
                    color="purple"
                    icon="arrow-down-tray"
                    class="cursor-pointer shadow-lg !px-6 !py-3 !text-base !font-semibold">
                    Télécharger votre fichier
                </flux:button>
            </div>
        @endif
    </div>
</section>
