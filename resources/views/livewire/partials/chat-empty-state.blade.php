<div class="flex items-start gap-6">
    <div class="flex-1 space-y-4">
        <div class="space-y-4">
            <p class="text-base font-normal text-black">
                Bienvenue dans le configurateur de pièces en tôle. Vous pouvez démarrer votre demande de fichier CAO de 3 manières :
            </p>
        </div>

        <div class="space-y-2">
            <div class="bg-white border-[0.5px] border-[#D7DBE0] rounded-lg p-4 flex gap-2">
                <div class="shrink-0 w-5 h-5 text-black">
                    <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M17 3a2.828 2.828 0 114 4L7.5 20.5 2 22l1.5-5.5L17 3z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </div>
                <div class="flex-1 space-y-2">
                    <p class="text-sm font-medium leading-[1.4] text-black">
                        Décrivez votre pièce dans le chat
                    </p>
                    <div class="text-xs font-normal leading-[1.4] text-soft-black space-y-2">
                        <p>
                            Expliquez directement en langage naturel ce que vous souhaitez concevoir, le système transformera votre description en fichier CAO.
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
                <div class="shrink-0 w-5 h-5 text-black">
                    <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8l-6-6z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        <path d="M14 2v6h6M16 13H8M16 17H8M10 9H8" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </div>
                <div class="flex-1 space-y-2">
                    <p class="text-sm font-medium leading-[1.4] text-black">
                        Importez un plan ou un pdf technique
                    </p>
                    <p class="text-xs font-normal leading-[1.4] text-soft-black">
                        Ajoutez un fichier pour que le chat s'appuie sur votre document existant.
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
                    <div class="absolute top-0 right-0 rounded-sm bg-purple-10">
                        <span class="px-3 py-1.5 text-xs font-medium text-[#7B46E4] bg-[#F2EDFC] rounded-sm">
                            Bientôt disponible
                        </span>
                    </div>
                </div>
            </div>

            <div class="relative bg-white border-[0.5px] border-[#D7DBE0] rounded-lg p-4 flex gap-2">
                <div class="shrink-0 w-5 h-5 text-black">
                    <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M20 7h-9M14 17H5M18 12H6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </div>
                <div class="flex-1 space-y-2">
                    <p class="text-sm font-medium leading-[1.4] text-black">
                        Ajoutez un fichier CAO
                    </p>
                    <p class="text-xs font-normal leading-[1.4] text-soft-black">
                        Chargez un fichier CAO existant pour demander des ajustements ou corrections.
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
                <div class="absolute top-0 right-0 rounded-sm bg-fichiers-10">
                    <span class="px-3 py-1.5 text-xs font-medium text-[#7B46E4] bg-[#F2EDFC] rounded-sm">
                        Bientôt disponible
                    </span>
                </div>
            </div>
        </div>
    </div>
</div>
