<section class="flex-1 min-w-0 bg-grey-background p-4">
    <div class="relative h-full w-full bg-white border border-grey-stroke rounded-xl overflow-hidden">
        <div x-data="cadStreamModal()"
             x-show="open"
             x-cloak
             class="absolute inset-0 z-50 flex items-start justify-center pt-8 bg-white">
            <div class="w-full max-w-4xl mx-4">
                <div class="bg-gradient-to-r from-violet-600 to-indigo-800 px-6 py-4 text-white flex items-center justify-between rounded-t-2xl">
                    <h3 class="text-lg font-semibold">Génération de votre fichier CAO</h3>
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

                    {{-- Error state (async flow — the job reports to Nightwatch and notifies
                         the user; a retry is just a new message in the chat). --}}
                    <template x-if="hasError">
                        <div class="mt-6 p-4 bg-red-50 border border-red-200 rounded-lg">
                            <div class="flex items-start gap-3">
                                <svg class="w-5 h-5 text-red-600 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                                <div class="flex-1">
                                    <p class="text-sm font-medium text-red-900 mb-1" x-text="errorMessage || 'Une erreur est survenue'"></p>
                                    <p class="text-xs text-red-700">
                                        L'équipe Tolery a été automatiquement notifiée. Vous pouvez réessayer en renvoyant votre demande dans le chat.
                                    </p>
                                    <button
                                        @click="close()"
                                        class="mt-3 inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 hover:bg-gray-200 rounded-lg transition-colors">
                                        Fermer
                                    </button>
                                </div>
                            </div>
                        </div>
                    </template>

                    {{-- Info: depuis Phase 2 (#152), la génération continue côté worker même si l'onglet ferme.
                         La promesse de notification est tenue par SendCompletionEmailIfUnreadJob — cloche
                         d'abord, email après 60s si l'user n'a pas vu la notif. --}}
                    <template x-if="!hasError">
                        <div class="mt-6 p-4 bg-violet-50 border border-violet-200 rounded-lg flex items-start gap-3">
                            <svg class="w-5 h-5 text-violet-600 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            <div class="flex-1">
                                <p class="text-sm font-medium text-violet-900 mb-1">La génération de votre fichier peut mettre quelques secondes à plusieurs minutes selon la complexité</p>
                                <p class="text-xs text-violet-700">Vous pouvez fermer cette fenêtre</p>
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

        @if(! $this->hasDownloadablePiece())
            <div class="absolute inset-0 flex flex-col items-center justify-center pointer-events-none p-8"
                 x-data="{ visible: true }"
                 x-show="visible"
                 @cad-model-stats.window="visible = false">

                <div class="w-80 h-auto flex items-center justify-center mb-8">
                    <img src="{{ asset('vendor/ai-cad/images/tolery-large-logo.svg') }}" alt="ToleryCAD" />
                </div>
                <p class="text-sm font-medium text-black text-center max-w-[310px]">
                    Votre pièce apparaîtra ici dès qu'elle sera générée.
                </p>
                <p class="text-sm font-bold text-violet-600 text-center max-w-[310px] mt-4">
                    Décrivez bien votre pièce en fonction du cube d'orientation.
                </p>
            </div>
        @endif

        @if($this->hasDownloadablePiece())
            <div class="absolute bottom-6 left-1/2 -translate-x-1/2 z-10 flex flex-col items-center gap-2">
                @if ($currentVersion = $this->getCurrentVersionLabel())
                    <div class="flex items-center gap-2 px-3 py-1.5 text-sm font-medium text-purple-700 bg-white/90 backdrop-blur-sm rounded-full shadow-md border border-purple-200">
                        <flux:icon.cube-transparent class="size-4" />
                        <span>{{ $currentVersion }}</span>
                    </div>
                @endif

                {{-- #2374 — Désactivé pendant la préparation pour éviter les reclics
                     (la modal « en cours de préparation » est déjà ouverte). --}}
                <flux:button
                    wire:click="initiateDownload"
                    :disabled="$this->showPreparingModal"
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
