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
        @click="if (didDrag) { didDrag = false; return; } open = !open"
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
            {{-- Stepper de versions — bascule entre les versions générées dans la session --}}
            <template x-if="versions.length > 1">
                <div class="flex items-center gap-2 text-xs">
                    <span class="text-gray-500">Version</span>
                    <div class="flex flex-wrap gap-1">
                        <template x-for="v in versions" :key="v.id">
                            <button type="button"
                                    @click="loadVersion(v.id)"
                                    :title="`${v.label} — ${v.date}`"
                                    class="inline-flex items-center justify-center min-w-[2rem] px-2 py-0.5 rounded-full text-xs font-semibold border transition-all"
                                    :class="v.id === currentVersionId
                                        ? 'bg-violet-600 text-white border-violet-600 shadow-sm'
                                        : 'bg-white text-violet-700 border-violet-200 hover:bg-violet-50 cursor-pointer'"
                                    :disabled="v.id === currentVersionId"
                                    x-text="v.label">
                            </button>
                        </template>
                    </div>
                </div>
            </template>

            {{-- Astuces — bouton compact qui toggle un panneau avec quelques tips --}}
            <div>
                <button type="button"
                        @click="tipsOpen = !tipsOpen"
                        class="inline-flex items-center gap-1.5 text-xs font-medium text-violet-700 hover:text-violet-900 transition-colors">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/>
                    </svg>
                    <span>Astuces</span>
                    <svg class="w-3 h-3 transition-transform" :class="tipsOpen ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                    </svg>
                </button>
                <div x-show="tipsOpen" x-cloak
                     x-transition:enter="transition ease-out duration-150"
                     x-transition:enter-start="opacity-0 -translate-y-1"
                     x-transition:enter-end="opacity-100 translate-y-0"
                     x-transition:leave="transition ease-in duration-100"
                     x-transition:leave-start="opacity-100 translate-y-0"
                     x-transition:leave-end="opacity-0 -translate-y-1"
                     class="mt-2 rounded-lg bg-violet-50/60 border border-violet-100 p-3 text-xs text-violet-900 space-y-1.5">
                    <div class="flex items-start gap-2">
                        <span class="text-violet-500">→</span>
                        <span>Cliquez sur une face, un perçage ou un pliage de la pièce pour le sélectionner et le modifier.</span>
                    </div>
                    <div class="flex items-start gap-2">
                        <span class="text-violet-500">→</span>
                        <span>Cliquez sur un badge (Perçage, Pliage, Congé…) pour parcourir les éléments d'un même type.</span>
                    </div>
                    <div class="flex items-start gap-2">
                        <span class="text-violet-500">→</span>
                        <span>Clic-gauche pour faire pivoter la pièce, clic-droit pour la déplacer, molette pour zoomer.</span>
                    </div>
                    <div class="flex items-start gap-2">
                        <span class="text-violet-500">→</span>
                        <span>Glissez l'entête de cette fenêtre pour la repositionner.</span>
                    </div>
                </div>
            </div>

            {{-- Index des features détectées sur la pièce — clic pour cycler --}}
            <template x-if="visibleFeaturesIndex">
                <div class="flex flex-wrap gap-1.5">
                    <template x-for="(bucket, type) in visibleFeaturesIndex" :key="type">
                        <button type="button"
                                @click="selectByFeatureType(type)"
                                :title="`Cliquer pour parcourir les ${bucket.count} ${featureTypeLabel(type).toLowerCase()}${bucket.count > 1 ? 's' : ''}`"
                                class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium border cursor-pointer hover:scale-105 hover:shadow-sm transition-all"
                                :class="featureTypeStyle(type)">
                            <span x-text="featureTypeLabel(type)"></span>
                            <span class="font-bold" x-text="bucket.count"></span>
                        </button>
                    </template>
                </div>
            </template>

            {{-- Sélection avec types de faces --}}
            <div class="space-y-2">
                <div class="flex items-center justify-between">
                    <div class="text-base font-semibold text-gray-900">Sélection</div>
                    <button
                        x-show="selection && !editMode"
                        @click.stop="enableEditMode()"
                        class="inline-flex items-center gap-1 px-2.5 py-1 text-xs font-medium text-white bg-violet-600 hover:bg-violet-700 rounded-lg transition-colors">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/>
                        </svg>
                        Modifier
                    </button>
                    <div x-show="editMode" class="flex gap-2">
                        <button @click.stop="cancelEdit()"
                                :disabled="submitting"
                                class="px-2.5 py-1 text-xs font-medium text-gray-600 hover:text-gray-700 bg-gray-100 hover:bg-gray-200 rounded-lg transition-colors disabled:opacity-50 disabled:cursor-not-allowed">
                            Annuler
                        </button>
                        <button @click.stop="saveEdits()"
                                :disabled="submitting"
                                class="inline-flex items-center gap-1.5 px-2.5 py-1 text-xs font-medium text-white bg-violet-600 hover:bg-violet-700 rounded-lg transition-colors disabled:opacity-60 disabled:cursor-not-allowed">
                            <svg x-show="submitting" class="w-3 h-3 animate-spin" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
                            </svg>
                            <span x-text="submitting ? 'Envoi…' : 'Valider'"></span>
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
                                      'bg-indigo-100 text-indigo-700': selection.faceType === 'bending',
                                  }"
                                  x-text="selection.metrics?.displayType || 'Face'">
                            </span>
                            <span class="text-gray-500 text-xs">ID: </span>
                            <span class="font-medium text-xs" x-text="selection.realFaceId || selection.id"></span>
                            <template x-if="selection.orientation">
                                <span class="ml-auto text-xs px-2 py-0.5 rounded-md font-bold tracking-wide bg-blue-100 text-blue-700"
                                      x-text="orientationLabel(selection.orientation)"></span>
                            </template>
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
                                        <input type="number" step="0.01" x-model.number="edits.length"
                                               @keydown.enter.prevent="saveEdits()"
                                               :disabled="submitting"
                                               class="w-20 px-2 py-0.5 text-sm border rounded disabled:opacity-50" />
                                    </template>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-500">Largeur</span>
                                    <template x-if="!editMode">
                                        <span class="font-medium" x-text="fmt(selection.metrics.width)"></span>
                                    </template>
                                    <template x-if="editMode">
                                        <input type="number" step="0.01" x-model.number="edits.width"
                                               @keydown.enter.prevent="saveEdits()"
                                               :disabled="submitting"
                                               class="w-20 px-2 py-0.5 text-sm border rounded disabled:opacity-50" />
                                    </template>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-500">Épaisseur</span>
                                    <template x-if="!editMode">
                                        <span class="font-medium" x-text="fmt(selection.metrics.thickness)"></span>
                                    </template>
                                    <template x-if="editMode">
                                        <input type="number" step="0.01" x-model.number="edits.thickness"
                                               @keydown.enter.prevent="saveEdits()"
                                               :disabled="submitting"
                                               class="w-20 px-2 py-0.5 text-sm border rounded disabled:opacity-50" />
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
                                            <input type="number" step="0.01" x-model.number="edits.radius"
                                                   @keydown.enter.prevent="saveEdits()"
                                                   :disabled="submitting"
                                                   class="w-20 px-2 py-0.5 text-sm border rounded disabled:opacity-50" />
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
                                            <input type="number" step="0.01" x-model.number="edits.diameter"
                                                   @keydown.enter.prevent="saveEdits()"
                                                   :disabled="submitting"
                                                   class="w-20 px-2 py-0.5 text-sm border rounded disabled:opacity-50" />
                                        </template>
                                    </div>
                                </template>

                                <div class="flex justify-between">
                                    <span class="text-gray-500">Profondeur</span>
                                    <template x-if="!editMode">
                                        <span class="font-medium" x-text="fmt(selection.metrics.depth)"></span>
                                    </template>
                                    <template x-if="editMode">
                                        <input type="number" step="0.01" x-model.number="edits.depth"
                                               @keydown.enter.prevent="saveEdits()"
                                               :disabled="submitting"
                                               class="w-20 px-2 py-0.5 text-sm border rounded disabled:opacity-50" />
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
                                        <input type="number" step="0.01" x-model.number="edits.diameter"
                                               @keydown.enter.prevent="saveEdits()"
                                               :disabled="submitting"
                                               class="w-20 px-2 py-0.5 text-sm border rounded disabled:opacity-50" />
                                    </template>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-500">Profondeur</span>
                                    <template x-if="!editMode">
                                        <span class="font-medium" x-text="fmt(selection.metrics.depth)"></span>
                                    </template>
                                    <template x-if="editMode">
                                        <input type="number" step="0.01" x-model.number="edits.depth"
                                               @keydown.enter.prevent="saveEdits()"
                                               :disabled="submitting"
                                               class="w-20 px-2 py-0.5 text-sm border rounded disabled:opacity-50" />
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
                                <div class="flex justify-between items-center">
                                    <span class="text-gray-500">Taraudage</span>
                                    <template x-if="!editMode">
                                        <span class="font-medium" x-text="selection.metrics.thread || getThreadFromDiameter(selection.metrics.diameter)"></span>
                                    </template>
                                    <template x-if="editMode">
                                        <select x-model="edits.thread"
                                                @keydown.enter.prevent="saveEdits()"
                                                :disabled="submitting"
                                                class="w-24 px-2 py-0.5 text-sm border rounded bg-white disabled:opacity-50">
                                            <option value="M1">M1</option>
                                            <option value="M1.2">M1.2</option>
                                            <option value="M1.4">M1.4</option>
                                            <option value="M1.6">M1.6</option>
                                            <option value="M2">M2</option>
                                            <option value="M2.5">M2.5</option>
                                            <option value="M3">M3</option>
                                            <option value="M4">M4</option>
                                            <option value="M5">M5</option>
                                            <option value="M6">M6</option>
                                            <option value="M8">M8</option>
                                            <option value="M10">M10</option>
                                            <option value="M12">M12</option>
                                            <option value="M14">M14</option>
                                            <option value="M16">M16</option>
                                            <option value="M18">M18</option>
                                            <option value="M20">M20</option>
                                        </select>
                                    </template>
                                </div>
                                <div class="flex justify-between items-center">
                                    <span class="text-gray-500">Ø perçage</span>
                                    <span class="font-medium text-gray-600" x-text="fmt(getDiameterFromThread(selection.metrics.thread || getThreadFromDiameter(selection.metrics.diameter)) || selection.metrics.diameter)"></span>
                                </div>
                                <div class="flex justify-between items-center">
                                    <span class="text-gray-500">Profondeur</span>
                                    <template x-if="!editMode">
                                        <span class="font-medium" x-text="selection.metrics.subtype === 'through' ? 'Traversant' : fmt(selection.metrics.depth)"></span>
                                    </template>
                                    <template x-if="editMode">
                                        <div class="flex items-center gap-2">
                                            <select x-model="edits.depthType"
                                                    @keydown.enter.prevent="saveEdits()"
                                                    :disabled="submitting"
                                                    class="w-28 px-2 py-0.5 text-sm border rounded bg-white disabled:opacity-50">
                                                <option value="through">Traversant</option>
                                                <option value="blind">Borgne</option>
                                            </select>
                                            <input x-show="edits.depthType === 'blind'" type="number" step="0.1" x-model.number="edits.depth"
                                                   @keydown.enter.prevent="saveEdits()"
                                                   :disabled="submitting"
                                                   class="w-16 px-2 py-0.5 text-sm border rounded disabled:opacity-50" placeholder="mm" />
                                        </div>
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
                                        <input type="number" step="0.01" x-model.number="edits.diameter"
                                               @keydown.enter.prevent="saveEdits()"
                                               :disabled="submitting"
                                               class="w-20 px-2 py-0.5 text-sm border rounded disabled:opacity-50" />
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
                                        <input type="number" step="0.01" x-model.number="edits.depth"
                                               @keydown.enter.prevent="saveEdits()"
                                               :disabled="submitting"
                                               class="w-20 px-2 py-0.5 text-sm border rounded disabled:opacity-50" />
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
                                        <input type="number" step="0.01" x-model.number="edits.radius"
                                               @keydown.enter.prevent="saveEdits()"
                                               :disabled="submitting"
                                               class="w-20 px-2 py-0.5 text-sm border rounded disabled:opacity-50" />
                                    </template>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-500">Aire</span>
                                    <span class="font-medium" x-text="fmtArea(selection.metrics.area)"></span>
                                </div>
                            </div>
                        </template>

                        {{-- PLIAGE (bending) --}}
                        <template x-if="selection.faceType === 'bending'">
                            <div class="space-y-1.5">
                                <div class="flex justify-between items-center">
                                    <span class="text-gray-500">Rayon intérieur</span>
                                    <template x-if="!editMode">
                                        <span class="font-medium" x-text="fmt(selection.metrics.inner?.radius || selection.metrics.radius)"></span>
                                    </template>
                                    <template x-if="editMode">
                                        <input type="number" step="0.1" x-model.number="edits.innerRadius"
                                               @keydown.enter.prevent="saveEdits()"
                                               :disabled="submitting"
                                               class="w-20 px-2 py-0.5 text-sm border rounded disabled:opacity-50" />
                                    </template>
                                </div>
                                <div class="flex justify-between items-center" x-show="selection.metrics.outer?.radius">
                                    <span class="text-gray-500">Rayon extérieur</span>
                                    <span class="font-medium" x-text="fmt(selection.metrics.outer?.radius)"></span>
                                </div>
                                <div class="flex justify-between" x-show="selection.metrics.angle">
                                    <span class="text-gray-500">Angle</span>
                                    <template x-if="!editMode">
                                        <span class="font-medium" x-text="selection.metrics.angle + '°'"></span>
                                    </template>
                                    <template x-if="editMode">
                                        <div class="flex items-center gap-1">
                                            <input type="number" step="1" x-model.number="edits.angle"
                                                   @keydown.enter.prevent="saveEdits()"
                                                   :disabled="submitting"
                                                   class="w-16 px-2 py-0.5 text-sm border rounded disabled:opacity-50" />
                                            <span class="text-gray-500 text-sm">°</span>
                                        </div>
                                    </template>
                                </div>
                                <div class="flex justify-between" x-show="selection.metrics.length">
                                    <span class="text-gray-500">Longueur</span>
                                    <span class="font-medium" x-text="fmt(selection.metrics.length)"></span>
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
                                        <input type="number" step="0.01" x-model.number="edits.length"
                                               @keydown.enter.prevent="saveEdits()"
                                               :disabled="submitting"
                                               class="w-20 px-2 py-0.5 text-sm border rounded disabled:opacity-50" />
                                    </template>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-500">Largeur</span>
                                    <template x-if="!editMode">
                                        <span class="font-medium" x-text="fmt(selection.metrics.width)"></span>
                                    </template>
                                    <template x-if="editMode">
                                        <input type="number" step="0.01" x-model.number="edits.width"
                                               @keydown.enter.prevent="saveEdits()"
                                               :disabled="submitting"
                                               class="w-20 px-2 py-0.5 text-sm border rounded disabled:opacity-50" />
                                    </template>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-500">Épaisseur</span>
                                    <template x-if="!editMode">
                                        <span class="font-medium" x-text="fmt(selection.metrics.thickness)"></span>
                                    </template>
                                    <template x-if="editMode">
                                        <input type="number" step="0.01" x-model.number="edits.thickness"
                                               @keydown.enter.prevent="saveEdits()"
                                               :disabled="submitting"
                                               class="w-20 px-2 py-0.5 text-sm border rounded disabled:opacity-50" />
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
                                        <input type="number" step="0.01" x-model.number="edits.length"
                                               @keydown.enter.prevent="saveEdits()"
                                               :disabled="submitting"
                                               class="w-20 px-2 py-0.5 text-sm border rounded disabled:opacity-50" />
                                    </template>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-500">Largeur</span>
                                    <template x-if="!editMode">
                                        <span class="font-medium" x-text="fmt(selection.metrics.width)"></span>
                                    </template>
                                    <template x-if="editMode">
                                        <input type="number" step="0.01" x-model.number="edits.width"
                                               @keydown.enter.prevent="saveEdits()"
                                               :disabled="submitting"
                                               class="w-20 px-2 py-0.5 text-sm border rounded disabled:opacity-50" />
                                    </template>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-500">Profondeur</span>
                                    <template x-if="!editMode">
                                        <span class="font-medium" x-text="fmt(selection.metrics.depth)"></span>
                                    </template>
                                    <template x-if="editMode">
                                        <input type="number" step="0.01" x-model.number="edits.depth"
                                               @keydown.enter.prevent="saveEdits()"
                                               :disabled="submitting"
                                               class="w-20 px-2 py-0.5 text-sm border rounded disabled:opacity-50" />
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
            <div class="space-y-3" @click.stop>
                <div class="text-base font-semibold text-gray-900">Matière</div>

                <flux:radio.group variant="segmented" x-model="materialFamily" @change="$nextTick(() => saveMaterialChoice())">
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
                        <span class="text-gray-500">Longueur</span>
                        <span class="font-semibold" x-text="fmt(stats.sizeX)"></span>
                    </div>
                    <div class="flex items-baseline gap-2">
                        <span class="text-gray-500">Largeur</span>
                        <span class="font-semibold" x-text="fmt(stats.sizeY)"></span>
                    </div>
                    <div class="flex items-baseline gap-2">
                        <span class="text-gray-500">Hauteur</span>
                        <span class="font-semibold" x-text="fmt(stats.sizeZ)"></span>
                    </div>
                    <div class="flex items-baseline gap-2">
                        <span class="text-gray-500">Épaisseur</span>
                        <span class="font-semibold" x-text="stats.thickness ? fmt(stats.thickness) : '—'"></span>
                    </div>
                    <div class="flex items-baseline gap-2">
                        <span class="text-gray-500">Poids</span>
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

                // Material densities in g/mm³ (g/cm³ / 1000)
                materialDensities: {
                    STEEL: 0.00785,      // 7.85 g/cm³
                    ALUMINUM: 0.00270,   // 2.70 g/cm³
                    STAINLESS: 0.00800,  // 8.00 g/cm³
                },

                // State alimenté par app.js (events window)
                stats: {sizeX: 0, sizeY: 0, sizeZ: 0, unit: 'mm', volume: 0, thickness: 0, weight: 0},
                selection: null,
                featuresIndex: null, // { hole: { count, features }, fillet: {...}, ... } — null si pièce sans données sémantiques
                featuresCycleIndex: {}, // { hole: 2, fillet: 0 } — index courant par type pour cycler entre les features du même type
                tipsOpen: false, // toggle du panneau d'astuces
                versions: [], // [{id, label, date}, ...] — versions disponibles dans le chat
                currentVersionId: null, // id du message dont la version est active dans le viewer

                // Edit mode
                editMode: false,
                submitting: false, // true entre Valider et l'arrivée d'une nouvelle sélection (regen confirmée)
                submittingTimer: null,
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
                didDrag: false,         // true si le pointeur s'est réellement déplacé pendant le drag — évite que le mouseup post-drag soit interprété comme un click qui toggle open

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
                        if (detail) {
                            // Calculate weight from volume and material density
                            const weight = this.calculateWeight(detail.volume);
                            this.stats = { ...detail, weight };
                        }
                    })
                    // Sélection — utilisée aussi comme signal de fin de regen
                    // (le viewer ré-émet une selection après reload du JSON régénéré)
                    window.addEventListener('cad-selection', ({detail}) => {
                        this.selection = detail
                        if (this.submitting) {
                            this.finishSubmitting()
                        }
                    })
                    // Index des features détectées (perçages, pliages, congés, etc.)
                    window.addEventListener('cad-features-index', ({detail}) => {
                        this.featuresIndex = detail
                        this.featuresCycleIndex = {} // reset les cycles à chaque nouveau modèle
                    })
                    // Liste des versions disponibles + version active dans le viewer
                    Livewire.on('cad-versions-updated', ({versions, currentVersionId}) => {
                        this.versions = Array.isArray(versions) ? versions : []
                        this.currentVersionId = currentVersionId ?? null
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
                    this.didDrag = false
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
                        // Au-delà de 3px de déplacement, on considère que c'est un drag
                        // et plus un click — empêche le toggle open/close à la fin du drag.
                        if (Math.abs(dx) > 3 || Math.abs(dy) > 3) {
                            this.didDrag = true
                        }
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
                // Helpers d'affichage. Précision adaptative : entier si entier
                // (ex. 100 → "100 mm"), sinon 1 décimale (ex. 2.5 → "2.5 mm").
                // Garantit la cohérence entre lecture et édition (les inputs
                // x-model.number affichent les valeurs brutes sans arrondi).
                fmt(v) {
                    if (v == null) return '—';
                    const num = +v;
                    return num % 1 === 0 ? `${num.toFixed(0)} mm` : `${num.toFixed(1)} mm`;
                },
                fmtArea(v) {
                    return (v == null) ? '—' : `${(+v).toFixed(0)} mm²`
                },
                fmtWeight(v) {
                    if (v == null) return '—';
                    // Toujours afficher en kg (standard en tôlerie)
                    const kg = v / 1000;
                    // Afficher 3 décimales si < 1kg, 2 sinon
                    return kg < 1 ? `${kg.toFixed(3)} kg` : `${kg.toFixed(2)} kg`;
                },
                fmtThickness() {
                    return '—'
                }, // branche quand tu auras l’info
                coord(c) {
                    if (!c) return '—';
                    const u = this.stats.unit || 'mm';
                    return `(${(c.x || 0).toFixed(1)}, ${(c.y || 0).toFixed(1)}, ${(c.z || 0).toFixed(1)}) ${u}`
                },

                // Traduction des orientations Onshape/FreeCad vers le français
                // utilisé partout dans l'interface (Avant/Arrière/Dessus/etc.)
                orientationLabel(o) {
                    if (!o) return '';
                    const labels = {
                        TOP: 'Dessus',
                        BOTTOM: 'Dessous',
                        FRONT: 'Avant',
                        REAR: 'Arrière',
                        BACK: 'Arrière',
                        LEFT: 'Gauche',
                        RIGHT: 'Droite',
                    };
                    return labels[o.toUpperCase().trim()] || o;
                },

                // Mapping des types de features (cohérent avec les badges
                // "Sélection" plus bas qui utilisent les mêmes familles de couleurs)
                featureTypeLabel(type) {
                    const labels = {
                        hole: 'Perçage',
                        thread: 'Taraudage',
                        fillet: 'Congé',
                        chamfer: 'Chanfrein',
                        countersink: 'Fraisurage',
                        bending: 'Pliage',
                        slot: 'Oblong',
                        oblong: 'Oblong',
                        cylindrical: 'Cylindre',
                        planar: 'Face plane',
                        box: 'Face',
                    };
                    return labels[type] || type;
                },
                // Whitelist des types de features à afficher dans les pills d'index.
                // On masque les types de faces "géométriques" (rectangular, square,
                // cylindrical, planar, box, …) au profit des features fonctionnelles
                // (perçages, taraudages, fraisurages, pliages, congés, chanfreins, oblongs).
                get visibleFeaturesIndex() {
                    if (!this.featuresIndex) return null;
                    const allowed = ['hole', 'countersink', 'thread', 'bending', 'fillet', 'chamfer', 'slot', 'oblong'];
                    const filtered = {};
                    for (const [type, bucket] of Object.entries(this.featuresIndex)) {
                        if (allowed.includes(type)) filtered[type] = bucket;
                    }
                    return Object.keys(filtered).length ? filtered : null;
                },
                // Bascule la version active du viewer vers le messageId donné.
                // Le composant Chatbot écoute cet event Livewire et émet
                // jsonEdgesLoaded + cad-versions-updated en retour.
                loadVersion(messageId) {
                    if (!messageId || messageId === this.currentVersionId) return;
                    Livewire.dispatch('loadVersionInViewer', { messageId });
                },

                // Au clic sur un badge d'index, on cycle entre les features
                // de ce type. Pour une feature multi-face (fillet, bending, etc.),
                // on n'envoie qu'un seul faceId — le viewer sélectionnera
                // automatiquement toutes les faces du feature via getGroupIndicesForFeature.
                selectByFeatureType(type) {
                    const bucket = this.featuresIndex?.[type];
                    if (!bucket || !bucket.features.length) return;
                    const currentIdx = this.featuresCycleIndex[type] ?? -1;
                    const nextIdx = (currentIdx + 1) % bucket.features.length;
                    this.featuresCycleIndex = { ...this.featuresCycleIndex, [type]: nextIdx };
                    const feature = bucket.features[nextIdx];
                    const faceId = feature.face_ids?.[0]
                        || feature.inner?.face_ids?.[0]
                        || feature.outer?.face_ids?.[0]
                        || feature.edge_ids?.[0];
                    if (!faceId) return;
                    window.dispatchEvent(new CustomEvent('cad-select-face', { detail: { faceId } }));
                },
                featureTypeStyle(type) {
                    const styles = {
                        hole: 'bg-orange-50 text-orange-700 border-orange-200',
                        thread: 'bg-red-50 text-red-700 border-red-200',
                        fillet: 'bg-green-50 text-green-700 border-green-200',
                        chamfer: 'bg-green-50 text-green-700 border-green-200',
                        countersink: 'bg-purple-50 text-purple-700 border-purple-200',
                        bending: 'bg-indigo-50 text-indigo-700 border-indigo-200',
                        slot: 'bg-amber-50 text-amber-700 border-amber-200',
                        oblong: 'bg-amber-50 text-amber-700 border-amber-200',
                        cylindrical: 'bg-green-50 text-green-700 border-green-200',
                        planar: 'bg-blue-50 text-blue-700 border-blue-200',
                        box: 'bg-blue-50 text-blue-700 border-blue-200',
                    };
                    return styles[type] || 'bg-gray-50 text-gray-700 border-gray-200';
                },

                // Mapping diamètre de perçage → taille de taraudage ISO métrique
                // Source: Diamètres de perçage standard pour filetage métrique (pas standard)
                threadSizes: {
                    'M1': 0.75, 'M1.2': 0.95, 'M1.4': 1.1, 'M1.6': 1.25,
                    'M2': 1.6, 'M2.5': 2.05, 'M3': 2.5, 'M4': 3.3, 'M5': 4.2,
                    'M6': 5.0, 'M8': 6.8, 'M10': 8.5, 'M12': 10.2, 'M14': 12.0,
                    'M16': 14.0, 'M18': 15.5, 'M20': 17.5
                },
                // Trouver la taille de taraudage à partir du diamètre de perçage
                getThreadFromDiameter(diameter) {
                    if (!diameter) return 'M3';
                    let closest = 'M3';
                    let minDiff = Infinity;
                    for (const [thread, drillDia] of Object.entries(this.threadSizes)) {
                        const diff = Math.abs(drillDia - diameter);
                        if (diff < minDiff) {
                            minDiff = diff;
                            closest = thread;
                        }
                    }
                    // Si la différence est trop grande (>0.5mm), c'est peut-être pas un taraudage standard
                    return minDiff < 0.5 ? closest : 'M' + Math.round(diameter);
                },
                // Obtenir le diamètre de perçage à partir de la taille de taraudage
                getDiameterFromThread(thread) {
                    if (!thread) return null;
                    return this.threadSizes[thread] || null;
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
                    // Recalculate weight with new material
                    if (this.stats.volume) {
                        this.stats.weight = this.calculateWeight(this.stats.volume);
                    }
                },
                // Calculate weight from volume (mm³) and material density
                calculateWeight(volumeMm3) {
                    if (!volumeMm3 || volumeMm3 <= 0) return 0;
                    const density = this.materialDensities[this.materialFamily] || this.materialDensities.STEEL;
                    // weight in grams = volume (mm³) × density (g/mm³)
                    return volumeMm3 * density;
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
                        // Taraudage (thread) - utilise le mapping diamètre → taraudage
                        thread: m.thread || this.getThreadFromDiameter(m.diameter),
                        depthType: m.subtype === 'through' ? 'through' : 'blind',
                        // Pliage (bending)
                        innerRadius: m.inner?.radius || m.radius || null,
                        angle: m.angle || null,
                    };
                },
                cancelEdit() {
                    this.editMode = false;
                    this.edits = {
                        length: null, width: null, thickness: null,
                        radius: null, diameter: null, depth: null, pitch: null,
                        thread: null, depthType: null, innerRadius: null, angle: null
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
                    // Collect all changes with simplified format
                    const changes = [];
                    const m = this.selection.metrics;
                    const faceId = this.selection.realFaceId || this.selection.id;
                    const faceType = this.selection.faceType;

                    // Compare original vs edited values - simplified format
                    if (this.edits.length && this.edits.length !== m.length) {
                        changes.push(`la longueur à ${this.fmt(this.edits.length)}`);
                    }
                    if (this.edits.width && this.edits.width !== m.width) {
                        changes.push(`la largeur à ${this.fmt(this.edits.width)}`);
                    }
                    if (this.edits.thickness && this.edits.thickness !== m.thickness) {
                        changes.push(`l'épaisseur à ${this.fmt(this.edits.thickness)}`);
                    }
                    if (this.edits.diameter && this.edits.diameter !== m.diameter) {
                        changes.push(`le diamètre à ${this.fmt(this.edits.diameter)}`);
                    }
                    if (this.edits.depth && this.edits.depth !== m.depth) {
                        changes.push(`la profondeur à ${this.fmt(this.edits.depth)}`);
                    }
                    if (this.edits.radius && this.edits.radius !== m.radius) {
                        changes.push(`le rayon à ${this.fmt(this.edits.radius)}`);
                    }

                    // Taraudage (thread) - modifications spécifiques
                    if (faceType === 'thread') {
                        const originalThread = m.thread || this.getThreadFromDiameter(m.diameter);
                        if (this.edits.thread && this.edits.thread !== originalThread) {
                            changes.push(`le taraudage à ${this.edits.thread}`);
                        }
                        const originalDepthType = m.subtype === 'through' ? 'through' : 'blind';
                        if (this.edits.depthType !== originalDepthType) {
                            const depthLabel = this.edits.depthType === 'through' ? 'traversant' : 'borgne';
                            changes.push(`le type à ${depthLabel}`);
                        }
                        if (this.edits.depthType === 'blind' && this.edits.depth && this.edits.depth !== m.depth) {
                            changes.push(`la profondeur à ${this.fmt(this.edits.depth)}`);
                        }
                    }

                    // Pliage (bending) - modifications spécifiques
                    if (faceType === 'bending') {
                        const originalInnerRadius = m.inner?.radius || m.radius;
                        if (this.edits.innerRadius && this.edits.innerRadius !== originalInnerRadius) {
                            changes.push(`le rayon intérieur à ${this.fmt(this.edits.innerRadius)}`);
                        }
                        if (this.edits.angle && this.edits.angle !== m.angle) {
                            changes.push(`l'angle à ${this.edits.angle}°`);
                        }
                    }

                    if (changes.length === 0) {
                        alert('Aucune modification détectée');
                        return;
                    }

                    if (this.submitting) {
                        return;
                    }

                    // Build simplified message: "Changer X à Y [Face ID: Z]"
                    // Le suffixe [Face ID: ...] est strippé/chip-ifié à l'affichage côté chat
                    // (cf. parseFaceContext dans chat-messages.blade.php) mais reste en DB
                    // pour donner du contexte au LLM lors de la regénération.
                    const message = `Changer ${changes.join(', ')} [Face ID: ${faceId}]`;

                    // Passe en état "envoi en cours" : on garde les valeurs saisies visibles
                    // et on désactive les inputs jusqu'à l'arrivée d'une nouvelle cad-selection
                    // (= regen terminée) ou un timeout de sécurité.
                    this.submitting = true;
                    if (this.submittingTimer) clearTimeout(this.submittingTimer);
                    this.submittingTimer = setTimeout(() => this.finishSubmitting(), 60000);

                    Livewire.dispatch('sendRegenerationRequest', { message });
                },
                finishSubmitting() {
                    if (this.submittingTimer) {
                        clearTimeout(this.submittingTimer);
                        this.submittingTimer = null;
                    }
                    this.submitting = false;
                    this.editMode = false;
                    this.edits = {
                        length: null, width: null, thickness: null,
                        radius: null, diameter: null, depth: null, pitch: null,
                        thread: null, depthType: null, innerRadius: null, angle: null
                    };
                },
            }
        }
    </script>
@endonce
