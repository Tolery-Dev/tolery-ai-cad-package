<section class="flex-1 bg-grey-background p-4">
    <div class="relative h-full w-full bg-white border border-grey-stroke rounded-xl overflow-hidden">
        <div x-data="cadStreamModal()"
             x-show="open"
             x-cloak
             class="absolute inset-0 z-50 flex items-start justify-center pt-8 bg-white">
            <div class="w-full max-w-4xl mx-4">
                <div class="bg-gradient-to-r from-violet-600 to-indigo-800 px-6 py-4 text-white flex items-center justify-between rounded-t-2xl">
                    <h3 class="text-lg font-semibold">Traitement en cours</h3>
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
                                      x-text="s.label">
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
                <div class="w-60 h-60 rounded-full bg-gradient-to-br from-violet-100 to-indigo-100 flex items-center justify-center mb-8">
                    <svg id="Calque_1" data-name="Calque 1" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1000 1000">
                      <defs>
                        <style>
                          .cls-1 {
                            fill: #252525;
                          }

                          .cls-2 {
                            fill: transparent;
                          }

                          .cls-3 {
                            fill: #7b46e4;
                          }
                        </style>
                      </defs>
                      <circle class="cls-2" cx="500.12" cy="500.12" r="500.12"/>
                      <g>
                        <path class="cls-3" d="M643.44,396.31c31.82,0,57.61-25.83,57.61-57.69s-25.79-57.69-57.61-57.69-57.61,25.83-57.61,57.69,25.79,57.69,57.61,57.69Z"/>
                        <path class="cls-1" d="M582.94,580.38c-2.85,3.18-8.56,9.08-17.19,14.68-3.67,2.38-7.52,4.49-11.54,6.3-10.99,4.95-23.05,7.45-35.83,7.45s-24.87-2.49-36.03-7.41c-11.18-4.94-20.89-12.04-28.88-21.09-7.85-8.89-14.06-19.61-18.48-31.85-4.41-12.11-6.62-25.61-6.62-40.13s2.24-27.36,6.64-39.31c4.45-12.1,10.74-22.49,18.67-30.87,7.97-8.37,17.65-15.01,28.78-19.73,11.13-4.7,23.55-7.04,35.92-7.04v-109.14c-31.34,0-60.81,4.9-87.61,14.57-26.66,9.64-49.99,23.5-69.37,41.21-19.3,17.66-34.72,39.43-45.77,64.7-11.06,25.27-16.68,54.08-16.68,85.61s5.61,60.7,16.71,86.56c11.06,25.84,26.49,48.2,45.85,66.47,19.4,18.27,42.74,32.6,69.37,42.59,26.75,10.02,56.19,15.11,87.51,15.11s60.76-5.09,87.51-15.11c26.63-9.98,50.07-24.32,69.69-42.62,1.28-1.19,10.58-10.59,22.21-23.33,1.42-1.56,2.57-2.82,3.26-3.59-36.82-21.33-73.64-42.67-110.46-64-1.75,2.67-4.26,6.17-7.66,9.97Z"/>
                      </g>
                    </svg>
                </div>
                <p class="text-sm font-medium text-black text-center max-w-[310px]">
                    Votre pièce apparaîtra ici dès qu'elle sera prête.
                </p>
            </div>
        @endif

        @if($stepExportUrl || $objExportUrl)
            <div class="absolute bottom-6 left-1/2 -translate-x-1/2 z-10">
                <flux:button
                    wire:click="initiateDownload"
                    variant="primary"
                    icon="arrow-down-tray"
                    class="cursor-pointer !bg-violet-600 hover:!bg-violet-700 !text-white shadow-lg !px-6 !py-3 !text-base !font-semibold">
                    Télécharger votre fichier
                </flux:button>
            </div>
        @endif
    </div>
</section>
