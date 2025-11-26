<section class="flex-1 bg-grey-background p-8">
    <div class="relative h-full w-full bg-white border border-grey-stroke rounded-xl overflow-hidden">
        <div x-data="cadStreamModal()"
             x-show="open"
             x-cloak
             class="absolute inset-0 z-50 flex items-start justify-center pt-8 bg-white dark:bg-zinc-950">
            <div class="w-full max-w-4xl mx-4">
                <div class="bg-gradient-to-r from-violet-600 to-indigo-800 px-6 py-4 text-white flex items-center justify-between rounded-t-2xl">
                    <h3 class="text-lg font-semibold">Processing</h3>
                    <div class="text-sm" x-text="`${completedSteps} out of 5 steps completed`"></div>
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

        <div id="viewer"
             wire:ignore
             data-screenshot-exists="{{ $screenshotUrl ? 'true' : 'false' }}"
             class="h-full w-full">
        </div>

        @include('ai-cad::partials.cad-config-panel', [
            'stepExportUrl' => $stepExportUrl,
            'objExportUrl' => $objExportUrl,
            'technicalDrawingUrl' => $technicalDrawingUrl,
            'screenshotUrl' => $screenshotUrl
        ])

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
