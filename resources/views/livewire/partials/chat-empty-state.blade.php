<div class="flex items-start gap-6">
    <div class="flex-1 space-y-4">
        <div class="space-y-4">
            <p class="text-base font-normal text-black">
                Bienvenue dans le configurateur intelligent de création de fichier CAO (STEP) sur-mesure et instantanément. Vous pouvez démarrer votre demande de fichier CAO de 3 manières :
            </p>
        </div>

        <div class="space-y-2">
            <div class="bg-white border-[0.5px] border-[#D7DBE0] rounded-lg p-4 flex gap-2">
                <div class="shrink-0 w-8 h-8">
                    <svg class="w-8 h-8" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <defs>
                            <linearGradient id="chatGradient" x1="0%" y1="0%" x2="100%" y2="100%">
                                <stop offset="0%" style="stop-color:#7b46e4;stop-opacity:1" />
                                <stop offset="100%" style="stop-color:#4F46E5;stop-opacity:1" />
                            </linearGradient>
                        </defs>
                        <path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2v10z" stroke="url(#chatGradient)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        <path d="M8 10h8M8 14h4" stroke="url(#chatGradient)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </div>
                <div class="flex-1 space-y-4">
                    <p class="text-sm font-medium leading-[1.4] text-black">
                        Décrivez votre pièce dans le chat (Text-to-CAD)
                    </p>
                    <div class="text-xs font-normal leading-[1.4] text-soft-black space-y-2">
                        <p>
                            Expliquez directement en langage naturel ce que vous souhaitez concevoir, notre intelligence artificielle transformera votre description en fichier CAO.
                        </p>
                        <p>Testez avec ces exemples :</p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        @php
                            // Tailwind palette for predefined prompt pills
                            $buttonColors = [
                                'bg-indigo-100 text-indigo-700',
                                'bg-amber-100 text-amber-700',
                                'bg-emerald-100 text-emerald-700',
                                'bg-rose-100 text-rose-700',
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

            <div class="relative bg-white border-[0.5px] border-[#D7DBE0] rounded-lg p-4 flex gap-2">
                <div class="shrink-0 w-8 h-8 pt-4">
                    <svg class="w-8 h-8" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <defs>
                            <linearGradient id="pdfGradient" x1="0%" y1="0%" x2="100%" y2="100%">
                                <stop offset="0%" style="stop-color:#7b46e4;stop-opacity:1" />
                                <stop offset="100%" style="stop-color:#4F46E5;stop-opacity:1" />
                            </linearGradient>
                        </defs>
                        <path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8l-6-6z" stroke="url(#pdfGradient)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        <path d="M14 2v6h6" stroke="url(#pdfGradient)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        <path d="M9 13h6M9 17h3" stroke="url(#pdfGradient)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        <rect x="7" y="9" width="2" height="2" fill="url(#pdfGradient)"/>
                    </svg>
                </div>
                <div class="flex-1 space-y-4 pt-4">
                    <p class="text-sm font-medium leading-[1.4] text-black">
                        Importez un plan ou pdf technique (PDF-to-CAD)
                    </p>
                    <p class="text-xs font-normal leading-[1.4] text-soft-black">
                        Ajouter le plan ou pdf technique de votre pièce, notre intelligence artificielle transformera votre plan ou pdf en fichier CAO (STEP).
                    </p>
                    <div class="flex gap-1">
                        <span class="px-3 py-1 rounded bg-grey-background border-[0.5px] border-grey-stroke text-xs font-medium text-soft-black">
                            pdf
                        </span>
                        <span class="px-3 py-1 rounded bg-grey-background border-[0.5px] border-grey-stroke text-xs font-medium text-soft-black">
                            png
                        </span>
                        <span class="px-3 py-1 rounded bg-grey-background border-[0.5px] border-grey-stroke text-xs font-medium text-soft-black">
                            jpg
                        </span>
                    </div>
                    <div class="absolute top-0 right-0 bg-purple-10">
                        <span class="px-3 py-1.5 text-xs font-medium text-[#7B46E4] bg-[#F2EDFC] rounded-tr-sm rounded-bl-sm">
                            Bientôt disponible
                        </span>
                    </div>
                </div>
            </div>

            <div class="relative bg-white border-[0.5px] border-[#D7DBE0] rounded-lg p-4 flex gap-2">
                <div class="shrink-0 w-8 h-8 pt-4">
                    <svg class="w-8 h-8" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <defs>
                            <linearGradient id="cadGradient" x1="0%" y1="0%" x2="100%" y2="100%">
                                <stop offset="0%" style="stop-color:#7b46e4;stop-opacity:1" />
                                <stop offset="100%" style="stop-color:#4F46E5;stop-opacity:1" />
                            </linearGradient>
                        </defs>
                        <path d="M21 16V8a2 2 0 00-1-1.73l-7-4a2 2 0 00-2 0l-7 4A2 2 0 003 8v8a2 2 0 001 1.73l7 4a2 2 0 002 0l7-4A2 2 0 0021 16z" stroke="url(#cadGradient)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        <path d="M3.27 6.96L12 12.01l8.73-5.05M12 22.08V12" stroke="url(#cadGradient)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </div>
                <div class="flex-1 space-y-4 pt-4">
                    <p class="text-sm font-medium leading-[1.4] text-black">
                        Importez votre fichier CAO (CAD-to-CAD)
                    </p>
                    <p class="text-xs font-normal leading-[1.4] text-soft-black">
                        Ajouter votre fichier CAO existant, notre intelligence artificielle corrigera celui-ci s'il est en erreur ou vous pourrez le modifier directement si besoin.
                    </p>
                    <div class="flex gap-1">
                        <span class="px-3 py-1 rounded bg-grey-background border-[0.5px] border-grey-stroke text-xs font-medium text-soft-black">
                            step
                        </span>
                        <span class="px-3 py-1 rounded bg-grey-background border-[0.5px] border-grey-stroke text-xs font-medium text-soft-black">
                            dxf
                        </span>
                    </div>
                </div>
                <div class="absolute top-0 right-0 bg-fichiers-10">
                    <span class="px-3 py-1.5 text-xs font-medium text-[#7B46E4] bg-[#F2EDFC] rounded-tr-sm rounded-bl-sm">
                        Bientôt disponible
                    </span>
                </div>
            </div>
        </div>
    </div>
</div>
