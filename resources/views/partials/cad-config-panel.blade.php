{{-- Fenêtre volante du configurateur CAD (draggable) --}}
<template x-teleport="body">
    <aside
        x-data="cadConfigPanel({
            initialStepUrl: @js($stepExportUrl ?? null),
            initialObjUrl: @js($objExportUrl ?? null),
            initialScreenshotUrl: @js($screenshotUrl ?? null),
            initialTechnicalDrawingUrl: @js($technicalDrawingUrl ?? null)
        })"
        :style="hasGeneratedInSession ? `position: fixed; top: 0; left: 0; transform: translate(${x}px, ${y}px); z-index: 9999;` : 'display: none;'"
        @dblclick.stop="open = !open"
        class="w-[360px] max-w-[90vw] border border-violet-500/80 ring-1 ring-violet-400/50 shadow-xl shadow-violet-500/10 rounded-2xl bg-white dark:bg-zinc-900 scroll-smooth overflow-hidden select-none"
        :class="open ? '[box-shadow:0_12px_30px_-6px_rgba(124,58,237,0.35),0_6px_18px_-8px_rgba(124,58,237,0.25)]' : ''"
    >
    {{-- Header (handle drag + clickable to toggle) --}}
    <div
        x-show="hasGeneratedInSession"
        @click="open = !open"
        @mousedown="startDrag($event)"
        @touchstart.passive="startDrag($event)"
        class="flex items-center justify-between px-4 py-3 bg-violet-50/60 cursor-pointer hover:bg-violet-100/60 transition-colors"
    >
        <div class="flex items-center gap-2 pointer-events-none">
            <span class="inline-flex h-6 w-6 items-center justify-center rounded-full bg-violet-600 text-white text-xs">⚙️</span>
            <h3 class="text-base font-bold text-violet-700">Configurez votre fichier CAO</h3>
        </div>

        <div class="pointer-events-none">
            <!-- Chevron qui pivote -->
            <svg xmlns="http://www.w3.org/2000/svg"
                 viewBox="0 0 24 24" fill="currentColor"
                 class="h-5 w-5 text-violet-700 transition-transform duration-200"
                 :class="open ? '' : 'rotate-180'">
                <path fill-rule="evenodd"
                      d="M12 8.47a.75.75 0 0 1 .53.22l5 5a.75.75 0 1 1-1.06 1.06L12 10.31l-4.47 4.47a.75.75 0 0 1-1.06-1.06l5-5a.75.75 0 0 1 .53-.22z"
                      clip-rule="evenodd"/>
            </svg>
        </div>
    </div>

    {{-- Contenu (collapsible) --}}
    <div
        x-show="hasGeneratedInSession"
        id="cad-config-panel"
        :aria-hidden="(!open).toString()"
        class="will-change-[max-height,opacity,transform] transition-[max-height,opacity,transform] duration-300 ease-[cubic-bezier(.22,1,.36,1)] transition-delay-75 overflow-y-auto"
        :class="open ? 'opacity-100 translate-y-0' : 'opacity-0 -translate-y-1'"
        x-bind:style="open ? 'max-height: calc(100vh - 140px)' : 'max-height: 0px; overflow: hidden;'"
    >
        <div class="p-4 space-y-4 select-text">
            {{-- Instructions (NEW - Priority) --}}
            <flux:callout icon="information-circle" size="sm" color="violet" class="text-violet-900">
                <flux:callout.text>
                    Cliquez sur une face, un perçage, un pliage... l'élément de votre pièce que vous souhaitez pour le modifier directement.
                </flux:callout.text>
            </flux:callout>

            {{-- Sélection avec types de faces --}}
            <div class="space-y-2">
                <div class="flex items-center justify-between">
                    <div class="text-base font-semibold text-gray-900">Sélection</div>
                    <button
                        x-show="selection && !editMode"
                        @click="enableEditMode()"
                        class="text-sm text-violet-600 hover:text-violet-700 font-semibold">
                        Modifier
                    </button>
                    <div x-show="editMode" class="flex gap-2">
                        <button @click="cancelEdit()" class="text-sm text-gray-600 hover:text-gray-700">
                            Annuler
                        </button>
                        <button @click="saveEdits()" class="text-sm text-violet-600 hover:text-violet-700 font-semibold">
                            Valider
                        </button>
                    </div>
                </div>

                <template x-if="selection">
                    <div class="text-sm space-y-2 rounded-xl bg-violet-50/60 border border-violet-100 p-3">
                        {{-- Badge type --}}
                        <div class="flex items-center gap-2">
                            <span class="text-xs px-2 py-0.5 rounded-full font-medium"
                                  :class="{
                                      'bg-blue-100 text-blue-700': selection.faceType === 'planar' || selection.faceType === 'box',
                                      'bg-green-100 text-green-700': selection.faceType === 'cylindrical' || selection.faceType === 'fillet',
                                      'bg-orange-100 text-orange-700': selection.faceType === 'hole',
                                      'bg-red-100 text-red-700': selection.faceType === 'thread',
                                      'bg-purple-100 text-purple-700': selection.faceType === 'countersink',
                                      'bg-amber-100 text-amber-700': selection.faceType === 'oblong',
                                  }"
                                  x-text="selection.metrics?.displayType || 'Face'">
                            </span>
                            <span class="text-gray-500 text-xs">ID: </span>
                            <span class="font-medium text-xs" x-text="selection.realFaceId || selection.id"></span>
                        </div>

                        {{-- Face PLANE --}}
                        <template x-if="selection.faceType === 'planar'">
                            <div class="space-y-1.5">
                                <div class="flex justify-between">
                                    <span class="text-gray-500">Longueur</span>
                                    <template x-if="!editMode">
                                        <span class="font-medium" x-text="fmt(selection.metrics.length)"></span>
                                    </template>
                                    <template x-if="editMode">
                                        <input type="number" step="0.01" x-model="edits.length"
                                               class="w-20 px-2 py-0.5 text-sm border rounded" />
                                    </template>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-500">Largeur</span>
                                    <template x-if="!editMode">
                                        <span class="font-medium" x-text="fmt(selection.metrics.width)"></span>
                                    </template>
                                    <template x-if="editMode">
                                        <input type="number" step="0.01" x-model="edits.width"
                                               class="w-20 px-2 py-0.5 text-sm border rounded" />
                                    </template>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-500">Épaisseur</span>
                                    <template x-if="!editMode">
                                        <span class="font-medium" x-text="fmt(selection.metrics.thickness)"></span>
                                    </template>
                                    <template x-if="editMode">
                                        <input type="number" step="0.01" x-model="edits.thickness"
                                               class="w-20 px-2 py-0.5 text-sm border rounded" />
                                    </template>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-500">Aire</span>
                                    <span class="font-medium" x-text="fmtArea(selection.metrics.area)"></span>
                                </div>
                            </div>
                        </template>

                        {{-- Face CYLINDRIQUE (Cylindre ou Bord arrondi) --}}
                        <template x-if="selection.faceType === 'cylindrical'">
                            <div class="space-y-1.5">
                                {{-- Afficher Rayon pour les bords arrondis, Diamètre pour les cylindres --}}
                                <template x-if="selection.metrics.displayType === 'Bord arrondi'">
                                    <div class="flex justify-between">
                                        <span class="text-gray-500">Rayon</span>
                                        <template x-if="!editMode">
                                            <span class="font-medium" x-text="fmt(selection.metrics.radius)"></span>
                                        </template>
                                        <template x-if="editMode">
                                            <input type="number" step="0.01" x-model="edits.radius"
                                                   class="w-20 px-2 py-0.5 text-sm border rounded" />
                                        </template>
                                    </div>
                                </template>

                                <template x-if="selection.metrics.displayType === 'Cylindre'">
                                    <div class="flex justify-between">
                                        <span class="text-gray-500">Diamètre</span>
                                        <template x-if="!editMode">
                                            <span class="font-medium" x-text="fmt(selection.metrics.diameter)"></span>
                                        </template>
                                        <template x-if="editMode">
                                            <input type="number" step="0.01" x-model="edits.diameter"
                                                   class="w-20 px-2 py-0.5 text-sm border rounded" />
                                        </template>
                                    </div>
                                </template>

                                <div class="flex justify-between">
                                    <span class="text-gray-500">Profondeur</span>
                                    <template x-if="!editMode">
                                        <span class="font-medium" x-text="fmt(selection.metrics.depth)"></span>
                                    </template>
                                    <template x-if="editMode">
                                        <input type="number" step="0.01" x-model="edits.depth"
                                               class="w-20 px-2 py-0.5 text-sm border rounded" />
                                    </template>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-500">Aire</span>
                                    <span class="font-medium" x-text="fmtArea(selection.metrics.area)"></span>
                                </div>
                            </div>
                        </template>

                        {{-- PERCAGE --}}
                        <template x-if="selection.faceType === 'hole'">
                            <div class="space-y-1.5">
                                <div class="flex justify-between">
                                    <span class="text-gray-500">Diamètre</span>
                                    <template x-if="!editMode">
                                        <span class="font-medium" x-text="fmt(selection.metrics.diameter)"></span>
                                    </template>
                                    <template x-if="editMode">
                                        <input type="number" step="0.01" x-model="edits.diameter"
                                               class="w-20 px-2 py-0.5 text-sm border rounded" />
                                    </template>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-500">Profondeur</span>
                                    <template x-if="!editMode">
                                        <span class="font-medium" x-text="fmt(selection.metrics.depth)"></span>
                                    </template>
                                    <template x-if="editMode">
                                        <input type="number" step="0.01" x-model="edits.depth"
                                               class="w-20 px-2 py-0.5 text-sm border rounded" />
                                    </template>
                                </div>
                                <div class="text-xs text-gray-500">
                                    Position : <span x-text="coord(selection.metrics.position)"></span>
                                </div>
                            </div>
                        </template>

                        {{-- TARAUDAGE --}}
                        <template x-if="selection.faceType === 'thread'">
                            <div class="space-y-1.5">
                                <div class="flex justify-between">
                                    <span class="text-gray-500">Diamètre</span>
                                    <template x-if="!editMode">
                                        <span class="font-medium" x-text="fmt(selection.metrics.diameter)"></span>
                                    </template>
                                    <template x-if="editMode">
                                        <input type="number" step="0.01" x-model="edits.diameter"
                                               class="w-20 px-2 py-0.5 text-sm border rounded" />
                                    </template>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-500">Profondeur</span>
                                    <template x-if="!editMode">
                                        <span class="font-medium" x-text="fmt(selection.metrics.depth)"></span>
                                    </template>
                                    <template x-if="editMode">
                                        <input type="number" step="0.01" x-model="edits.depth"
                                               class="w-20 px-2 py-0.5 text-sm border rounded" />
                                    </template>
                                </div>
                                <div class="text-xs text-gray-500">
                                    Position : <span x-text="coord(selection.metrics.position)"></span>
                                </div>
                            </div>
                        </template>

                        {{-- FRAISURAGE (countersink) --}}
                        <template x-if="selection.faceType === 'countersink'">
                            <div class="space-y-1.5">
                                <div class="flex justify-between">
                                    <span class="text-gray-500">Diamètre</span>
                                    <template x-if="!editMode">
                                        <span class="font-medium" x-text="fmt(selection.metrics.diameter)"></span>
                                    </template>
                                    <template x-if="editMode">
                                        <input type="number" step="0.01" x-model="edits.diameter"
                                               class="w-20 px-2 py-0.5 text-sm border rounded" />
                                    </template>
                                </div>
                                <div class="flex justify-between" x-show="selection.metrics.angle">
                                    <span class="text-gray-500">Angle</span>
                                    <span class="font-medium" x-text="selection.metrics.angle + '°'"></span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-500">Profondeur</span>
                                    <template x-if="!editMode">
                                        <span class="font-medium" x-text="fmt(selection.metrics.depth)"></span>
                                    </template>
                                    <template x-if="editMode">
                                        <input type="number" step="0.01" x-model="edits.depth"
                                               class="w-20 px-2 py-0.5 text-sm border rounded" />
                                    </template>
                                </div>
                                <div class="text-xs text-gray-500">
                                    Position : <span x-text="coord(selection.metrics.position)"></span>
                                </div>
                            </div>
                        </template>

                        {{-- CONGE (fillet) --}}
                        <template x-if="selection.faceType === 'fillet'">
                            <div class="space-y-1.5">
                                <div class="flex justify-between">
                                    <span class="text-gray-500">Rayon</span>
                                    <template x-if="!editMode">
                                        <span class="font-medium" x-text="fmt(selection.metrics.radius)"></span>
                                    </template>
                                    <template x-if="editMode">
                                        <input type="number" step="0.01" x-model="edits.radius"
                                               class="w-20 px-2 py-0.5 text-sm border rounded" />
                                    </template>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-500">Aire</span>
                                    <span class="font-medium" x-text="fmtArea(selection.metrics.area)"></span>
                                </div>
                            </div>
                        </template>

                        {{-- FACE / BOX (from FreeCad API) --}}
                        <template x-if="selection.faceType === 'box'">
                            <div class="space-y-1.5">
                                <div class="flex justify-between">
                                    <span class="text-gray-500">Longueur</span>
                                    <template x-if="!editMode">
                                        <span class="font-medium" x-text="fmt(selection.metrics.length)"></span>
                                    </template>
                                    <template x-if="editMode">
                                        <input type="number" step="0.01" x-model="edits.length"
                                               class="w-20 px-2 py-0.5 text-sm border rounded" />
                                    </template>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-500">Largeur</span>
                                    <template x-if="!editMode">
                                        <span class="font-medium" x-text="fmt(selection.metrics.width)"></span>
                                    </template>
                                    <template x-if="editMode">
                                        <input type="number" step="0.01" x-model="edits.width"
                                               class="w-20 px-2 py-0.5 text-sm border rounded" />
                                    </template>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-500">Épaisseur</span>
                                    <template x-if="!editMode">
                                        <span class="font-medium" x-text="fmt(selection.metrics.thickness)"></span>
                                    </template>
                                    <template x-if="editMode">
                                        <input type="number" step="0.01" x-model="edits.thickness"
                                               class="w-20 px-2 py-0.5 text-sm border rounded" />
                                    </template>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-500">Aire</span>
                                    <span class="font-medium" x-text="fmtArea(selection.metrics.area)"></span>
                                </div>
                            </div>
                        </template>

                        {{-- OBLONG (slot with rounded ends) --}}
                        <template x-if="selection.faceType === 'oblong'">
                            <div class="space-y-1.5">
                                <div class="flex justify-between">
                                    <span class="text-gray-500">Longueur</span>
                                    <template x-if="!editMode">
                                        <span class="font-medium" x-text="fmt(selection.metrics.straight_length || selection.metrics.length)"></span>
                                    </template>
                                    <template x-if="editMode">
                                        <input type="number" step="0.01" x-model="edits.length"
                                               class="w-20 px-2 py-0.5 text-sm border rounded" />
                                    </template>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-500">Largeur</span>
                                    <template x-if="!editMode">
                                        <span class="font-medium" x-text="fmt(selection.metrics.width)"></span>
                                    </template>
                                    <template x-if="editMode">
                                        <input type="number" step="0.01" x-model="edits.width"
                                               class="w-20 px-2 py-0.5 text-sm border rounded" />
                                    </template>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-500">Profondeur</span>
                                    <template x-if="!editMode">
                                        <span class="font-medium" x-text="fmt(selection.metrics.depth)"></span>
                                    </template>
                                    <template x-if="editMode">
                                        <input type="number" step="0.01" x-model="edits.depth"
                                               class="w-20 px-2 py-0.5 text-sm border rounded" />
                                    </template>
                                </div>
                                <div class="text-xs text-gray-500" x-show="selection.metrics.position">
                                    Position : <span x-text="coord(selection.metrics.position)"></span>
                                </div>
                            </div>
                        </template>
                    </div>
                </template>

                <template x-if="!selection">
                    <div class="text-sm text-gray-500 rounded-xl bg-gray-50 border border-gray-200 p-3">
                        Aucune face sélectionnée.
                    </div>
                </template>
            </div>

            <flux:separator/>

            {{-- Matière --}}
            <div class="space-y-3">
                <div class="text-base font-semibold text-gray-900">Matière</div>

                <flux:radio.group variant="segmented" x-model="materialFamily" @change="saveMaterialChoice()">
                    @foreach (\Tolery\AiCad\Enum\MaterialFamily::cases() as $material)
                        <flux:radio :value="$material->value" :label="$material->label()" />
                    @endforeach
                </flux:radio.group>

            </div>

            <flux:separator/>

            {{-- Détails / Dimensions globales --}}
            <div class="rounded-xl bg-violet-50/60 border border-violet-100 p-4">
                <div class="flex items-center justify-between">
                    <div class="text-base font-semibold text-gray-900">Détails</div>
                </div>

                <div class="mt-3 grid grid-cols-2 gap-x-6 gap-y-2 text-sm">
                    <div class="flex items-baseline gap-2">
                        <span class="text-gray-500">longueur</span>
                        <span class="font-semibold" x-text="fmt(stats.sizeX)"></span>
                    </div>
                    <div class="flex items-baseline gap-2">
                        <span class="text-gray-500">largeur</span>
                        <span class="font-semibold" x-text="fmt(stats.sizeY)"></span>
                    </div>
                    <div class="flex items-baseline gap-2">
                        <span class="text-gray-500">hauteur</span>
                        <span class="font-semibold" x-text="fmt(stats.sizeZ)"></span>
                    </div>
                    <div class="flex items-baseline gap-2">
                        <span class="text-gray-500">épaisseur</span>
                        <span class="font-semibold" x-text="stats.thickness ? fmt(stats.thickness) : '—'"></span>
                    </div>
                    <div class="flex items-baseline gap-2">
                        <span class="text-gray-500">poids</span>
                        <span class="font-semibold" x-text="stats.weight ? fmtWeight(stats.weight) : '—'"></span>
                    </div>
                </div>
            </div>

            <flux:separator/>

            {{-- Outil de mesure --}}
            <div class="space-y-2">
                <div class="flex items-center justify-between">
                    <div class="text-base font-medium text-gray-900">Outil de mesure</div>
                    <flux:button @click="toggleMeasure()" size="sm">
                        <span x-text="measureEnabled ? 'Désactiver' : 'Activer'"></span>
                    </flux:button>
                </div>
                <div class="text-sm text-gray-500 pl-1">
                    Cliquez sur deux points pour afficher la distance
                </div>
            </div>

            {{-- Barre d’actions rapide --}}
            <div class="flex items-center justify-between -mt-1">
              <div class="text-sm text-gray-500"></div>
              <flux:button variant="outline" size="sm" icon="arrows-pointing-in" @click="recenter()">
                Recentrer vue
              </flux:button>
            </div>

            {{--
            Section téléchargements (si fichiers disponibles)
            <flux:separator/>
            <div x-show="hasGeneratedInSession" class="space-y-3">
                <div class="text-lg font-semibold text-gray-900">Télécharger les fichiers</div>
                <template x-if="!hasExports()">
                    <div class="rounded-lg border border-amber-200 bg-amber-50/70 text-amber-800 px-4 py-3 text-sm">
                        Abonnez-vous pour récupérer vos créations dès qu'elles sont prêtes.
                    </div>
                </template>
                <div class="grid grid-cols-1 gap-2" x-show="hasExports()">
                    <template x-if="exports.step">
                        <a :href="exports.step"
                           target="_blank"
                           rel="noopener noreferrer"
                           class="flex items-center justify-between px-4 py-3 rounded-lg border border-violet-200 bg-violet-50/50 hover:bg-violet-100/70 transition-colors group">
                            <div class="flex items-center gap-3">
                                <div class="h-8 w-8 rounded-lg bg-violet-600 text-white grid place-items-center text-xs font-semibold">
                                    3D
                                </div>
                                <div>
                                    <div class="text-sm font-medium text-gray-900">Fichier STEP</div>
                                    <div class="text-xs text-gray-500">Format CAO standard</div>
                                </div>
                            </div>
                            <svg class="h-5 w-5 text-violet-600 group-hover:translate-y-0.5 transition-transform" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M9 19l3 3m0 0l3-3m-3 3V10" />
                            </svg>
                        </a>
                    </template>

                    <template x-if="exports.obj">
                        <a :href="exports.obj"
                           target="_blank"
                           rel="noopener noreferrer"
                           class="flex items-center justify-between px-4 py-3 rounded-lg border border-violet-200 bg-violet-50/50 hover:bg-violet-100/70 transition-colors group">
                            <div class="flex items-center gap-3">
                                <div class="h-8 w-8 rounded-lg bg-indigo-600 text-white grid place-items-center text-xs font-semibold">
                                    OBJ
                                </div>
                                <div>
                                    <div class="text-sm font-medium text-gray-900">Fichier OBJ</div>
                                    <div class="text-xs text-gray-500">Modèle 3D mesh</div>
                                </div>
                            </div>
                            <svg class="h-5 w-5 text-indigo-600 group-hover:translate-y-0.5 transition-transform" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M9 19l3 3m0 0l3-3m-3 3V10" />
                            </svg>
                        </a>
                    </template>

                    <template x-if="exports.technical_drawing">
                        <a :href="exports.technical_drawing"
                           target="_blank"
                           rel="noopener noreferrer"
                           class="flex items-center justify-between px-4 py-3 rounded-lg border border-violet-200 bg-violet-50/50 hover:bg-violet-100/70 transition-colors group">
                            <div class="flex items-center gap-3">
                                <div class="h-8 w-8 rounded-lg bg-purple-600 text-white grid place-items-center text-xs font-semibold">
                                    PDF
                                </div>
                                <div>
                                    <div class="text-sm font-medium text-gray-900">Plan technique</div>
                                    <div class="text-xs text-gray-500">Mise en plan PDF</div>
                                </div>
                            </div>
                            <svg class="h-5 w-5 text-purple-600 group-hover:translate-y-0.5 transition-transform" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M9 19l3 3m0 0l3-3m-3 3V10" />
                            </svg>
                        </a>
                    </template>
                </div>
                <template x-if="exports.screenshot">
                    <div class="rounded-lg border border-violet-200 bg-violet-50/50 p-3 space-y-2">
                        <div class="flex items-center gap-2">
                            <div class="h-6 w-6 rounded bg-violet-600 text-white grid place-items-center text-xs font-semibold">
                                📸
                            </div>
                            <div class="text-sm font-medium text-gray-900">Screenshot de la pièce</div>
                        </div>
                        <img :src="exports.screenshot"
                                alt="Screenshot de la pièce"
                                class="w-full h-auto rounded-lg border border-violet-200 shadow-sm"
                                loading="lazy">
                        <a :href="exports.screenshot"
                            download
                            class="flex items-center justify-center gap-2 px-3 py-2 rounded-lg bg-violet-600 text-white text-sm font-medium hover:bg-violet-700 transition-colors">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M9 19l3 3m0 0l3-3m-3 3V10" />
                            </svg>
                            Télécharger
                        </a>
                    </div>
                </template>
            </div>
            --}}
        </div>
    </aside>
</template>

@once
    <script>
        function cadConfigPanel(config = {}) {
            return {
                // UI
                open: false, // État d'ouverture/fermeture du panneau
                showDetails: true,
                measureEnabled: false,

                // Material choice (synced with Livewire)
                materialFamily: @js($chat->material_family?->value ?? 'STEEL'),

                // State alimenté par app.js (events window)
                stats: {sizeX: 0, sizeY: 0, sizeZ: 0, unit: 'mm'},
                selection: null,

                // Edit mode
                editMode: false,
                edits: {
                    length: null,
                    width: null,
                    thickness: null,
                    radius: null,
                    diameter: null,
                    depth: null,
                    pitch: null
                },

                // position (transform translate)
                x: 0, y: 0,
                startX: 0, startY: 0,   // position souris au début du drag
                baseX: 0, baseY: 0,     // position du panneau au début du drag
                dragging: false,

                // Flag pour savoir si une pièce a été générée dans cette session
                hasGeneratedInSession: Boolean(
                    config.initialStepUrl ||
                    config.initialObjUrl ||
                    config.initialTechnicalDrawingUrl ||
                    config.initialScreenshotUrl
                ),

                // Data
                partName: 'Pièce 001',

                // Exports disponibles (initialisés depuis Livewire puis mis à jour par événements)
                exports: {
                    step: config.initialStepUrl || null,
                    obj: config.initialObjUrl || null,
                    technical_drawing: config.initialTechnicalDrawingUrl || null,
                    screenshot: config.initialScreenshotUrl || null,
                },

                init() {
                    // Position initiale : centré en haut du viewer (zone droite de l'écran)
                    this.$nextTick(() => {
                        const panelWidth = 360
                        const viewportWidth = window.innerWidth
                        
                        // Le viewer occupe ~65% de l'écran (100% - 35% du chat panel)
                        // On centre le panneau dans cette zone
                        const chatPanelWidth = viewportWidth * 0.35
                        const viewerWidth = viewportWidth - chatPanelWidth
                        const viewerCenterX = chatPanelWidth + (viewerWidth / 2)
                        
                        // Position centrée horizontalement dans le viewer, en haut
                        this.x = viewerCenterX - (panelWidth / 2)
                        this.y = 80 // En dessous du header

                        // Valide que la position est dans l'écran
                        this.clampToViewport()
                    })

                    // Réajuste sur resize
                    window.addEventListener('resize', () => {
                        this.clampToViewport()
                    })

                    // Dimensions globales
                    // On garde window.addEventListener pour compatibilité avec app.js
                    window.addEventListener('cad-model-stats', ({detail}) => {
                        if (detail) this.stats = detail
                    })
                    // Sélection
                    window.addEventListener('cad-selection', ({detail}) => {
                        this.selection = detail
                    })
                    // Écoute les événements d'export depuis Livewire
                    Livewire.on('cad-exports-updated', ({step, obj, technical_drawing, screenshot}) => {
                        this.exports.step = step || null
                        this.exports.obj = obj || null
                        this.exports.technical_drawing = technical_drawing || null
                        this.exports.screenshot = screenshot || null
                        // Marque qu'une pièce a été générée dans cette session
                        this.hasGeneratedInSession = true
                        // Dispatch browser event for simple panel
                        this.$dispatch('cad-screenshot-updated', { url: screenshot })
                    })
                },
                hasExports() {
                    return this.exports.step || this.exports.obj || this.exports.technical_drawing
                },
                // ---- Drag & drop ----
                startDrag(e) {
                    this.dragging = true
                    const isTouch = e.type === 'touchstart'
                    const p = isTouch ? e.touches[0] : e
                    this.startX = p.clientX
                    this.startY = p.clientY
                    this.baseX = this.x
                    this.baseY = this.y

                    const move = (ev) => {
                        if (!this.dragging) return
                        const pp = ev.type.startsWith('touch') ? ev.touches[0] : ev
                        const dx = pp.clientX - this.startX
                        const dy = pp.clientY - this.startY
                        this.x = this.baseX + dx
                        this.y = this.baseY + dy
                        this.clampToViewport()
                    }
                    const end = () => {
                        this.dragging = false
                        window.removeEventListener('mousemove', move)
                        window.removeEventListener('mouseup', end)
                        window.removeEventListener('touchmove', move)
                        window.removeEventListener('touchend', end)
                        // persist
                        localStorage.setItem('cadPanelPos', JSON.stringify({x: this.x, y: this.y}))
                    }

                    window.addEventListener('mousemove', move)
                    window.addEventListener('mouseup', end)
                    window.addEventListener('touchmove', move, {passive: true})
                    window.addEventListener('touchend', end)
                },
                clampToViewport() {
                    // contraintes : 12px de marge
                    const panel = this.$el
                    const rect = panel.getBoundingClientRect()
                    const w = rect.width, h = rect.height
                    const maxX = window.innerWidth - w - 12
                    const maxY = window.innerHeight - h - 12
                    this.x = Math.min(Math.max(this.x, 12), Math.max(maxX, 12))
                    this.y = Math.min(Math.max(this.y, 12), Math.max(maxY, 12))
                },
                // Helpers d’affichage
                fmt(v) {
                    return (v == null) ? '—' : `${(+v).toFixed(0)} mm`
                },
                fmtArea(v) {
                    return (v == null) ? '—' : `${(+v).toFixed(0)} mm²`
                },
                fmtWeight(v) {
                    if (v == null) return '—';
                    // Convertir en kg si > 1000g
                    if (v >= 1000) {
                        return `${(v / 1000).toFixed(2)} kg`;
                    }
                    return `${(+v).toFixed(0)} g`;
                },
                fmtThickness() {
                    return '—'
                }, // branche quand tu auras l’info
                coord(c) {
                    if (!c) return '—';
                    const u = this.stats.unit || 'mm';
                    return `(${(c.x || 0).toFixed(1)}, ${(c.y || 0).toFixed(1)}, ${(c.z || 0).toFixed(1)}) ${u}`
                },

                // Features
                toggleMeasure() {
                    this.measureEnabled = !this.measureEnabled
                    Livewire.dispatch('toggleMeasureMode', {enabled: this.measureEnabled})
                    if (!this.measureEnabled) Livewire.dispatch('resetMeasure')
                },
                recenter() {
                  // Demande au viewer de se recentrer
                  this.$dispatch('viewer-fit');
                },
                saveMaterialChoice() {
                    Livewire.dispatch('updateMaterialFamily', {
                        materialFamily: this.materialFamily
                    });
                },
                enableEditMode() {
                    this.editMode = true;
                    // Initialize edits from current selection
                    const m = this.selection.metrics;
                    this.edits = {
                        length: m.length || null,
                        width: m.width || null,
                        thickness: m.thickness || null,
                        radius: m.radius || null,
                        diameter: m.diameter || null,
                        depth: m.depth || null,
                        pitch: m.pitch || null,
                    };
                },
                cancelEdit() {
                    this.editMode = false;
                    this.edits = {
                        length: null, width: null, thickness: null,
                        radius: null, diameter: null, depth: null, pitch: null
                    };
                },
                // Build technical badge for bot message
                buildTechnicalBadge() {
                    const m = this.selection.metrics;
                    const type = m.displayType || 'Face';
                    
                    // For features with semantic data from FreeCad
                    if (m.type && m.subtype) {
                        const parts = [type];
                        
                        // Add subtype info
                        if (m.subtype === 'through') parts.push('traversant');
                        if (m.subtype === 'blind') parts.push('borgne');
                        if (m.subtype === 'threaded' || m.subtype === 'tapped') parts.push('taraudé');
                        
                        // Add key dimensions
                        if (m.diameter) parts.push(`Ø${m.diameter}mm`);
                        if (m.depth) parts.push(`profondeur ${m.depth}mm`);
                        
                        // Add thread info if present
                        if (m.thread && m.thread.designation) {
                            parts.push(`filetage ${m.thread.designation}`);
                        }
                        
                        return parts.join(' ');
                    }
                    
                    // Fallback for geometric detection
                    const parts = [type];
                    if (m.diameter) parts.push(`Ø${m.diameter}mm`);
                    if (m.depth) parts.push(`prof. ${m.depth}mm`);
                    if (m.length && m.width) parts.push(`${m.length}×${m.width}mm`);
                    
                    return parts.join(' ');
                },

                // Build face context string (same format as FaceSelectionManager)
                buildFaceContext() {
                    const sel = this.selection;
                    const faceId = sel.realFaceId || sel.id;
                    const m = sel.metrics;
                    
                    let ctx = `Face Selection: ID[${faceId}]`;
                    
                    // Add position if available
                    if (m.position || sel.centroid) {
                        const pos = m.position || sel.centroid;
                        ctx += ` Position[center(${(pos.x || 0).toFixed(1)}, ${(pos.y || 0).toFixed(1)}, ${(pos.z || 0).toFixed(1)})]`;
                    }
                    
                    // Add bounding box if available
                    if (sel.bbox) {
                        ctx += ` BBox[Size(${(sel.bbox.x || 0).toFixed(1)}, ${(sel.bbox.y || 0).toFixed(1)}, ${(sel.bbox.z || 0).toFixed(1)})]`;
                    }
                    
                    // Add area if available
                    if (m.area || sel.area) {
                        const area = m.area || sel.area;
                        ctx += ` Area[${area.toFixed(0)} mm²]`;
                    }
                    
                    // Add feature type info
                    const displayType = m.displayType || 'Face';
                    ctx += ` Type[${displayType}]`;
                    
                    // Add specific metrics based on face type
                    if (m.diameter) ctx += ` Diameter[${m.diameter.toFixed(2)} mm]`;
                    if (m.depth) ctx += ` Depth[${m.depth.toFixed(2)} mm]`;
                    if (m.length && m.width) ctx += ` Dimensions[${m.length.toFixed(2)}×${m.width.toFixed(2)} mm]`;
                    
                    return `[FACE_CONTEXT: ${ctx}]`;
                },

                saveEdits() {
                    // Collect all changes
                    const changes = [];
                    const m = this.selection.metrics;
                    const displayType = m.displayType || 'Face';
                    const faceId = this.selection.realFaceId || this.selection.id;

                    // Compare original vs edited values
                    if (this.edits.length && this.edits.length !== m.length) {
                        changes.push(`Longueur: ${this.fmt(m.length)} → ${this.fmt(this.edits.length)}`);
                    }
                    if (this.edits.width && this.edits.width !== m.width) {
                        changes.push(`Largeur: ${this.fmt(m.width)} → ${this.fmt(this.edits.width)}`);
                    }
                    if (this.edits.thickness && this.edits.thickness !== m.thickness) {
                        changes.push(`Épaisseur: ${this.fmt(m.thickness)} → ${this.fmt(this.edits.thickness)}`);
                    }
                    if (this.edits.diameter && this.edits.diameter !== m.diameter) {
                        changes.push(`Diamètre: ${this.fmt(m.diameter)} → ${this.fmt(this.edits.diameter)}`);
                    }
                    if (this.edits.depth && this.edits.depth !== m.depth) {
                        changes.push(`Profondeur: ${this.fmt(m.depth)} → ${this.fmt(this.edits.depth)}`);
                    }
                    if (this.edits.radius && this.edits.radius !== m.radius) {
                        changes.push(`Rayon: ${this.fmt(m.radius)} → ${this.fmt(this.edits.radius)}`);
                    }

                    if (changes.length === 0) {
                        alert('Aucune modification détectée');
                        return;
                    }

                    // Build face context (pastille format)
                    const faceContext = this.buildFaceContext();
                    
                    // Build technical badge with feature details
                    const technicalBadge = this.buildTechnicalBadge();
                    
                    // Build message with face context for the API
                    const message = `Modifier ${technicalBadge}: ${changes.join(' ; ')} ${faceContext}`;

                    // Send to Livewire
                    Livewire.dispatch('sendRegenerationRequest', { message });

                    // Reset edit mode
                    this.cancelEdit();
                },
            }
        }
    </script>
@endonce
