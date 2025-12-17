<div class="flex items-start gap-6">
    <div class="flex-1 space-y-4">
        <div class="space-y-2">
            <div class="bg-white border-[0.5px] border-[#D7DBE0] rounded-lg p-4 flex gap-2">
                <div class="shrink-0 w-10 h-10">
                    <img src="{{ asset('vendor/ai-cad/images/chat.svg') }}" alt="Text to CAD">
                </div>
                <div class="flex-1 space-y-4">
                    <p class="text-sm font-medium leading-[1.4] text-black">
                        Décrivez une pièce simple dans le chat (Text-to-CAD)
                    </p>
                    <div class="text-xs font-normal leading-[1.4] text-soft-black space-y-2">
                        <p>
                            Expliquer directement en langage naturel ce que vous souhaitez concevoir, notre intelligence artificielle transformera votre description en fichier CAO.
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
                <div class="shrink-0 w-10 h-10 pt-4">
                    <img src="{{ asset('vendor/ai-cad/images/pdf.svg') }}" alt="PDF to CAD">
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
                <div class="shrink-0 w-10 h-10 pt-4">
                    <img src="{{ asset('vendor/ai-cad/images/cad.svg') }}" alt="Tolery CAD files">
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
