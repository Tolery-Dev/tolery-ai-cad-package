<div class="space-y-8">
    {{-- Header Card --}}
    <flux:card>
        <div class="flex items-start justify-between gap-6">
            <div class="flex-1 space-y-6">
                {{-- Title and Session ID --}}
                <div>
                    <flux:heading size="xl" level="1" class="mb-2">
                        {{ $chat->name ?: 'Conversation sans nom' }}
                    </flux:heading>
                    @if($chat->session_id)
                        <div x-data="{ copied: false }" class="flex items-center gap-2 text-sm text-zinc-500 dark:text-zinc-400">
                            <flux:icon.hashtag class="size-4" />
                            <code class="font-mono bg-zinc-100 dark:bg-zinc-800 px-2 py-0.5 rounded">{{ $chat->session_id }}</code>
                            <button
                                type="button"
                                @click="navigator.clipboard.writeText('{{ $chat->session_id }}'); copied = true; setTimeout(() => copied = false, 2000)"
                                class="p-1 rounded hover:bg-zinc-100 dark:hover:bg-zinc-800 text-zinc-400 hover:text-zinc-600 transition-colors"
                                title="Copier">
                                <svg x-show="!copied" class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                                </svg>
                                <svg x-show="copied" x-cloak class="size-4 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                </svg>
                            </button>
                        </div>
                    @endif
                </div>

                {{-- Meta Grid --}}
                <div class="grid grid-cols-2 lg:grid-cols-5 gap-6">
                    {{-- Team --}}
                    <div class="space-y-1">
                        <p class="text-sm text-zinc-500 dark:text-zinc-400">Équipe</p>
                        <p class="font-medium text-zinc-900 dark:text-zinc-100">{{ $chat->team?->name ?? '-' }}</p>
                    </div>

                    {{-- User --}}
                    <div class="space-y-1">
                        <p class="text-sm text-zinc-500 dark:text-zinc-400">Utilisateur</p>
                        @if($chat->user)
                            <p class="font-medium text-zinc-900 dark:text-zinc-100">{{ $chat->user->full_name }}</p>
                            <p class="text-sm text-zinc-500 dark:text-zinc-400 truncate">{{ $chat->user->email }}</p>
                        @else
                            <p class="font-medium text-zinc-900 dark:text-zinc-100">-</p>
                        @endif
                    </div>

                    {{-- Material --}}
                    <div class="space-y-1">
                        <p class="text-sm text-zinc-500 dark:text-zinc-400">Matériau</p>
                        @if($chat->material_family)
                            <flux:badge color="zinc">{{ $chat->material_family->label() }}</flux:badge>
                        @else
                            <p class="font-medium text-zinc-900 dark:text-zinc-100">-</p>
                        @endif
                    </div>

                    {{-- Status --}}
                    <div class="space-y-1">
                        <p class="text-sm text-zinc-500 dark:text-zinc-400">Statut</p>
                        <div class="flex items-center gap-2">
                            @if($chat->has_generated_piece)
                                <flux:badge color="green">
                                    <flux:icon.check-circle class="size-3.5" />
                                    Pièce générée
                                </flux:badge>
                            @else
                                <flux:badge color="zinc">
                                    <flux:icon.clock class="size-3.5" />
                                    En cours
                                </flux:badge>
                            @endif
                            @if($chat->trashed())
                                <flux:badge color="red">Supprimée</flux:badge>
                            @endif
                        </div>
                    </div>

                    {{-- Date --}}
                    <div class="space-y-1">
                        <p class="text-sm text-zinc-500 dark:text-zinc-400">Créée le</p>
                        <p class="font-medium text-zinc-900 dark:text-zinc-100">{{ $chat->created_at->format('d/m/Y à H:i') }}</p>
                    </div>
                </div>
            </div>

            {{-- Actions --}}
            <div class="flex flex-col items-end gap-3">
                @if($chat->has_generated_piece)
                    <flux:button
                        wire:click="downloadZip"
                        variant="primary"
                        icon="arrow-down-tray">
                        Télécharger ZIP
                    </flux:button>
                @endif
            </div>
        </div>
    </flux:card>

    {{-- Messages Section --}}
    <div>
        <div class="flex items-center gap-3 mb-4">
            <flux:heading size="lg" level="2">Messages</flux:heading>
            <flux:badge color="zinc" size="sm">{{ $chat->messages->count() }}</flux:badge>
        </div>

        <div class="space-y-4">
            @forelse($chat->messages as $message)
                <flux:card wire:key="message-{{ $message->id }}" class="overflow-hidden">
                    <div class="flex gap-4">
                        {{-- Avatar/Icon --}}
                        <div class="shrink-0">
                            @if($message->role === 'user')
                                <div class="flex items-center justify-center size-10 rounded-full bg-violet-100 dark:bg-violet-900/30">
                                    <flux:icon.user class="size-5 text-violet-600 dark:text-violet-400" />
                                </div>
                            @else
                                <div class="flex items-center justify-center size-10 rounded-full bg-blue-100 dark:bg-blue-900/30">
                                    <flux:icon.sparkles class="size-5 text-blue-600 dark:text-blue-400" />
                                </div>
                            @endif
                        </div>

                        {{-- Content --}}
                        <div class="flex-1 min-w-0 space-y-3">
                            {{-- Header --}}
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    @if($message->role === 'user')
                                        <flux:badge color="purple" size="sm">Utilisateur</flux:badge>
                                        @if($message->user)
                                            <span class="text-sm text-zinc-600 dark:text-zinc-400">{{ $message->user->name }}</span>
                                        @endif
                                    @else
                                        <flux:badge color="blue" size="sm">
                                            <flux:icon.sparkles class="size-3" />
                                            Assistant
                                        </flux:badge>
                                        @if($message->getVersionLabel())
                                            <flux:badge color="green" size="sm">{{ $message->getVersionLabel() }}</flux:badge>
                                        @endif
                                    @endif
                                </div>
                                <span class="text-xs text-zinc-400">{{ $message->created_at->format('d/m/Y H:i:s') }}</span>
                            </div>

                            {{-- Message Text --}}
                            @if($message->message)
                                @php
                                    $trimmed = trim($message->message);
                                    $isDfmError = $message->role === 'assistant' && isset($dfmErrorCodes[$trimmed]);
                                @endphp
                                @if($isDfmError)
                                    <div class="flex items-start gap-2 text-amber-800 dark:text-amber-200">
                                        <flux:icon.exclamation-triangle class="size-5 shrink-0 text-amber-500 mt-0.5" />
                                        <div>
                                            <span class="text-xs font-mono text-amber-500">Code {{ $trimmed }}</span>
                                            <p class="text-sm mt-0.5">{{ $dfmErrorCodes[$trimmed] }}</p>
                                        </div>
                                    </div>
                                @else
                                    <div class="prose prose-sm dark:prose-invert max-w-none text-zinc-700 dark:text-zinc-300 prose-p:my-1 prose-ul:my-2 prose-ol:my-2 prose-li:my-0.5 prose-pre:my-2 prose-code:text-violet-700 prose-code:bg-violet-50 prose-code:px-1 prose-code:py-0.5 prose-code:rounded dark:prose-code:bg-violet-500/10 dark:prose-code:text-violet-300">
                                        {!! \Illuminate\Support\Str::markdown($message->message, [
                                            'html_input' => 'strip',
                                            'allow_unsafe_links' => false,
                                        ]) !!}
                                    </div>
                                @endif
                            @endif

                            {{-- Screenshot --}}
                            @if($message->ai_screenshot_path)
                                <div class="pt-3">
                                    <p class="text-xs font-medium text-zinc-500 dark:text-zinc-400 mb-2">Aperçu de la pièce</p>
                                    <img
                                        src="{{ $message->getScreenshotUrl() }}"
                                        alt="Screenshot {{ $message->getVersionLabel() ?? 'pièce' }}"
                                        class="max-w-lg rounded-lg border border-zinc-200 dark:border-zinc-700 shadow-sm">
                                </div>
                            @endif

                            {{-- 3D Viewer (click to open) --}}
                            @if($message->ai_json_edge_path)
                                <div
                                    x-data="adminViewer({{ Js::from($message->getJSONEdgeUrl()) }})"
                                    wire:ignore
                                    class="pt-3"
                                >
                                    <button
                                        type="button"
                                        x-show="!open"
                                        @click="toggle()"
                                        class="inline-flex items-center gap-2 px-3 py-1.5 text-sm font-medium text-violet-700 bg-violet-50 hover:bg-violet-100 dark:text-violet-300 dark:bg-violet-900/30 dark:hover:bg-violet-900/50 rounded-lg transition-colors cursor-pointer"
                                    >
                                        <flux:icon.cube-transparent class="size-4" />
                                        Voir en 3D
                                    </button>

                                    <div x-show="open" x-cloak x-collapse>
                                        <div class="flex items-center justify-between mb-2 mt-2">
                                            <p class="text-xs font-medium text-zinc-500 dark:text-zinc-400">Visualisation 3D</p>
                                            <button
                                                type="button"
                                                @click="toggle()"
                                                class="text-xs text-zinc-400 hover:text-zinc-600 transition-colors"
                                            >Fermer</button>
                                        </div>
                                        <div class="relative rounded-lg overflow-hidden border border-zinc-200 dark:border-zinc-700" style="height: 450px;">
                                            <div x-show="loading" class="absolute inset-0 z-10 flex items-center justify-center bg-white/80 dark:bg-zinc-900/80">
                                                <div class="flex flex-col items-center gap-3">
                                                    <flux:icon.arrow-path class="size-8 text-violet-600 animate-spin" />
                                                    <span class="text-sm text-zinc-500">Chargement du modèle 3D...</span>
                                                </div>
                                            </div>
                                            <div x-show="error" x-cloak class="absolute inset-0 z-10 flex items-center justify-center bg-white/80 dark:bg-zinc-900/80">
                                                <div class="flex flex-col items-center gap-3">
                                                    <flux:icon.exclamation-triangle class="size-8 text-red-500" />
                                                    <span class="text-sm text-red-600" x-text="error"></span>
                                                </div>
                                            </div>
                                            <div x-ref="viewer" class="w-full h-full"></div>
                                        </div>
                                    </div>
                                </div>
                            @endif

                            {{-- Generated Files --}}
                            @if($message->ai_cad_path || $message->ai_step_path || $message->ai_technical_drawing_path || $message->ai_json_edge_path)
                                <div class="pt-3 border-t border-zinc-100 dark:border-zinc-800">
                                    <p class="text-xs font-medium text-zinc-500 dark:text-zinc-400 mb-2">Fichiers générés</p>
                                    <div class="flex flex-wrap gap-2">
                                        @if($message->ai_step_path)
                                            <a href="{{ $message->getStepUrl() }}" target="_blank" download class="no-underline">
                                                <flux:badge color="green" size="sm" class="cursor-pointer hover:opacity-80 transition-opacity">
                                                    <flux:icon.document-arrow-down class="size-3.5" />
                                                    STEP
                                                </flux:badge>
                                            </a>
                                        @endif
                                        @if($message->ai_cad_path)
                                            <a href="{{ $message->getObjUrl() }}" target="_blank" download class="no-underline">
                                                <flux:badge color="blue" size="sm" class="cursor-pointer hover:opacity-80 transition-opacity">
                                                    <flux:icon.cube class="size-3.5" />
                                                    OBJ
                                                </flux:badge>
                                            </a>
                                        @endif
                                        @if($message->ai_technical_drawing_path)
                                            <a href="{{ $message->getTechnicalDrawingUrl() }}" target="_blank" class="no-underline">
                                                <flux:badge color="purple" size="sm" class="cursor-pointer hover:opacity-80 transition-opacity">
                                                    <flux:icon.document class="size-3.5" />
                                                    PDF
                                                </flux:badge>
                                            </a>
                                        @endif
                                        @if($message->ai_json_edge_path)
                                            <a href="{{ $message->getJSONEdgeUrl() }}" target="_blank" download class="no-underline">
                                                <flux:badge color="zinc" size="sm" class="cursor-pointer hover:opacity-80 transition-opacity">
                                                    <flux:icon.code-bracket class="size-3.5" />
                                                    JSON
                                                </flux:badge>
                                            </a>
                                        @endif
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>
                </flux:card>
            @empty
                <flux:card>
                    <div class="text-center py-12">
                        <flux:icon.chat-bubble-left-right class="size-12 text-zinc-300 dark:text-zinc-600 mx-auto mb-4" />
                        <p class="text-zinc-500 dark:text-zinc-400">Aucun message dans cette conversation</p>
                    </div>
                </flux:card>
            @endforelse
        </div>
    </div>
</div>

@script
<script>
    Alpine.data('adminViewer', (jsonUrl) => ({
        jsonUrl,
        open: false,
        loading: false,
        error: null,
        _renderer: null,
        _animationId: null,
        _loaded: false,

        toggle() {
            this.open = !this.open;
            if (this.open && !this._loaded) {
                this.$nextTick(() => this.loadModel());
            }
        },

        async loadModel() {
            this.loading = true;
            this.error = null;

            try {
                // Use shared cache on window to avoid re-importing Three.js per viewer
                if (!window._adminViewerThree) {
                    const THREE = await import('https://cdn.jsdelivr.net/npm/three@0.170.0/+esm');
                    const { OrbitControls } = await import('https://cdn.jsdelivr.net/npm/three@0.170.0/examples/jsm/controls/OrbitControls.js/+esm');
                    window._adminViewerThree = { THREE, OrbitControls };
                }
                const { THREE, OrbitControls } = window._adminViewerThree;

                const response = await fetch(this.jsonUrl);
                if (!response.ok) throw new Error('Impossible de charger le fichier 3D');
                const jsonData = await response.json();

                const container = this.$refs.viewer;
                const width = container.clientWidth;
                const height = container.clientHeight;

                const scene = new THREE.Scene();
                scene.background = new THREE.Color(0xfcfcfc);

                const camera = new THREE.PerspectiveCamera(45, width / height, 0.1, 10000);
                const renderer = new THREE.WebGLRenderer({ antialias: true });
                renderer.setSize(width, height);
                renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));
                renderer.toneMapping = THREE.ACESFilmicToneMapping;
                renderer.toneMappingExposure = 1.2;
                container.appendChild(renderer.domElement);

                const controls = new OrbitControls(camera, renderer.domElement);
                controls.enableDamping = true;
                controls.dampingFactor = 0.08;

                // Studio lighting (matches frontend chatbot viewer)
                scene.add(new THREE.AmbientLight(0xffffff, 0.4));
                scene.add(new THREE.HemisphereLight(0xffffff, 0xe0e0e0, 0.5));
                const key = new THREE.DirectionalLight(0xffffff, 1.8);
                key.position.set(4, 5, 3);
                scene.add(key);
                const fill = new THREE.DirectionalLight(0xffffff, 0.6);
                fill.position.set(-4, 3, 2);
                scene.add(fill);
                const rim = new THREE.DirectionalLight(0xffffff, 0.8);
                rim.position.set(-3, 4, -3);
                scene.add(rim);
                const top = new THREE.DirectionalLight(0xffffff, 0.5);
                top.position.set(0, 5, 0);
                scene.add(top);

                const positions = [];
                if (jsonData.faces && jsonData.faces.bodies) {
                    for (const body of jsonData.faces.bodies) {
                        for (const face of (body.faces || [])) {
                            for (const facet of (face.facets || [])) {
                                const vtx = facet.vertices;
                                if (!vtx || vtx.length < 3) continue;
                                if (vtx.length === 3) {
                                    positions.push(vtx[0].x, vtx[0].y, vtx[0].z, vtx[1].x, vtx[1].y, vtx[1].z, vtx[2].x, vtx[2].y, vtx[2].z);
                                } else {
                                    for (let i = 2; i < vtx.length; i++) {
                                        positions.push(vtx[0].x, vtx[0].y, vtx[0].z, vtx[i-1].x, vtx[i-1].y, vtx[i-1].z, vtx[i].x, vtx[i].y, vtx[i].z);
                                    }
                                }
                            }
                        }
                    }
                } else if (jsonData.objects) {
                    for (const obj of jsonData.objects) {
                        const verts = obj.vertices || [];
                        for (const f of (obj.facets || [])) {
                            const idx = Array.isArray(f.vertices) ? f.vertices : f;
                            if (idx.length >= 3) {
                                positions.push(verts[idx[0]][0], verts[idx[0]][1], verts[idx[0]][2], verts[idx[1]][0], verts[idx[1]][1], verts[idx[1]][2], verts[idx[2]][0], verts[idx[2]][1], verts[idx[2]][2]);
                            }
                        }
                    }
                }

                const geometry = new THREE.BufferGeometry();
                geometry.setAttribute('position', new THREE.Float32BufferAttribute(positions, 3));
                geometry.computeVertexNormals();

                // Studio environment map (matches frontend createEnvironmentMap)
                const envSize = 1024;
                const envCanvas = document.createElement('canvas');
                envCanvas.width = envSize; envCanvas.height = envSize;
                const envCtx = envCanvas.getContext('2d');
                const bgGrad = envCtx.createLinearGradient(0, 0, 0, envSize);
                bgGrad.addColorStop(0, '#ffffff');
                bgGrad.addColorStop(0.3, '#f0f0f2');
                bgGrad.addColorStop(0.6, '#d8d8dc');
                bgGrad.addColorStop(1, '#c0c0c8');
                envCtx.fillStyle = bgGrad;
                envCtx.fillRect(0, 0, envSize, envSize);
                envCtx.globalCompositeOperation = 'lighten';
                const sb1 = envCtx.createRadialGradient(envSize*0.2, envSize*0.2, 0, envSize*0.2, envSize*0.2, envSize*0.4);
                sb1.addColorStop(0, 'rgba(255,255,255,0.8)'); sb1.addColorStop(1, 'rgba(255,255,255,0)');
                envCtx.fillStyle = sb1; envCtx.fillRect(0, 0, envSize, envSize);
                const sb2 = envCtx.createRadialGradient(envSize*0.8, envSize*0.25, 0, envSize*0.8, envSize*0.25, envSize*0.35);
                sb2.addColorStop(0, 'rgba(255,255,255,0.7)'); sb2.addColorStop(1, 'rgba(255,255,255,0)');
                envCtx.fillStyle = sb2; envCtx.fillRect(0, 0, envSize, envSize);
                const envMap = new THREE.CanvasTexture(envCanvas);
                envMap.mapping = THREE.EquirectangularReflectionMapping;

                // Normal map — steel grain (matches frontend createNormalMap('acier'))
                const nmSize = 512;
                const nmCanvas = document.createElement('canvas');
                nmCanvas.width = nmSize; nmCanvas.height = nmSize;
                const nmCtx = nmCanvas.getContext('2d');
                const nmData = nmCtx.createImageData(nmSize, nmSize);
                for (let i = 0; i < nmData.data.length; i += 4) {
                    const noise = Math.random() * 20 - 10;
                    nmData.data[i] = Math.max(0, Math.min(255, 128 + noise));
                    nmData.data[i+1] = Math.max(0, Math.min(255, 128 + noise * 0.5));
                    nmData.data[i+2] = 255;
                    nmData.data[i+3] = 255;
                }
                nmCtx.putImageData(nmData, 0, 0);
                const normalMap = new THREE.CanvasTexture(nmCanvas);
                normalMap.wrapS = THREE.RepeatWrapping;
                normalMap.wrapT = THREE.RepeatWrapping;
                normalMap.repeat.set(4, 4);

                const material = new THREE.MeshPhysicalMaterial({
                    color: '#4a4f54',
                    metalness: 1,
                    roughness: 0.55,
                    clearcoat: 0.05,
                    clearcoatRoughness: 0,
                    reflectivity: 0.7,
                    normalMap: normalMap,
                    normalScale: new THREE.Vector2(0.6, 0.6),
                    envMap: envMap,
                    envMapIntensity: 1.0,
                    side: THREE.DoubleSide,
                });
                scene.add(new THREE.Mesh(geometry, material));

                const edges = new THREE.EdgesGeometry(geometry, 30);
                scene.add(new THREE.LineSegments(edges, new THREE.LineBasicMaterial({ color: 0x333333 })));

                const box = new THREE.Box3().setFromObject(scene);
                const center = box.getCenter(new THREE.Vector3());
                const size = box.getSize(new THREE.Vector3());
                const dist = Math.max(size.x, size.y, size.z) * 2;

                camera.position.set(center.x + dist * 0.6, center.y + dist * 0.4, center.z + dist * 0.7);
                camera.lookAt(center);
                controls.target.copy(center);
                controls.update();

                this._renderer = renderer;
                const animate = () => {
                    this._animationId = requestAnimationFrame(animate);
                    controls.update();
                    renderer.render(scene, camera);
                };
                animate();

                new ResizeObserver(() => {
                    const w = container.clientWidth, h = container.clientHeight;
                    if (w && h) {
                        camera.aspect = w / h;
                        camera.updateProjectionMatrix();
                        renderer.setSize(w, h);
                    }
                }).observe(container);

                this._loaded = true;
                this.loading = false;
            } catch (e) {
                console.error('[AdminViewer]', e);
                this.error = e.message || 'Erreur lors du chargement';
                this.loading = false;
            }
        }
    }));
</script>
@endscript
