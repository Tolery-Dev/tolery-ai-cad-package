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

    {{-- 3D Viewer Section --}}
    @php $versions = $this->getViewerVersions(); @endphp
    @if(count($versions) > 0)
        <flux:card
            x-data="adminViewer(@js($versions))"
            x-init="loadVersion(versions.length - 1)"
            wire:ignore
        >
            <div class="flex items-center justify-between mb-4">
                <div class="flex items-center gap-3">
                    <flux:heading size="lg" level="2">Visualisation 3D</flux:heading>
                    <flux:badge color="zinc" size="sm" x-text="versions[currentIndex]?.label ?? ''"></flux:badge>
                </div>
                <div class="flex items-center gap-2" x-show="versions.length > 1">
                    <template x-for="(v, i) in versions" :key="i">
                        <button
                            type="button"
                            @click="loadVersion(i)"
                            :class="i === currentIndex
                                ? 'bg-violet-600 text-white'
                                : 'bg-zinc-100 text-zinc-600 hover:bg-zinc-200 dark:bg-zinc-800 dark:text-zinc-400 dark:hover:bg-zinc-700'"
                            class="px-3 py-1 text-sm font-medium rounded-full transition-colors"
                            x-text="v.label">
                        </button>
                    </template>
                </div>
            </div>

            <div class="relative rounded-lg overflow-hidden border border-zinc-200 dark:border-zinc-700" style="height: 500px;">
                {{-- Loading overlay --}}
                <div x-show="loading" class="absolute inset-0 z-10 flex items-center justify-center bg-white/80 dark:bg-zinc-900/80">
                    <div class="flex flex-col items-center gap-3">
                        <flux:icon.arrow-path class="size-8 text-violet-600 animate-spin" />
                        <span class="text-sm text-zinc-500">Chargement du modèle 3D...</span>
                    </div>
                </div>

                {{-- Error overlay --}}
                <div x-show="error" x-cloak class="absolute inset-0 z-10 flex items-center justify-center bg-white/80 dark:bg-zinc-900/80">
                    <div class="flex flex-col items-center gap-3">
                        <flux:icon.exclamation-triangle class="size-8 text-red-500" />
                        <span class="text-sm text-red-600" x-text="error"></span>
                    </div>
                </div>

                <div x-ref="viewer" class="w-full h-full"></div>
            </div>
        </flux:card>

        <script>
            document.addEventListener('alpine:init', () => {
                Alpine.data('adminViewer', (versions) => ({
                    versions,
                    currentIndex: 0,
                    loading: false,
                    error: null,
                    renderer: null,
                    scene: null,
                    camera: null,
                    controls: null,
                    animationId: null,
                    threeLoaded: false,
                    THREE: null,
                    OrbitControls: null,

                    async ensureThreeLoaded() {
                        if (this.threeLoaded) return;
                        this.THREE = await import('https://cdn.jsdelivr.net/npm/three@0.170.0/+esm');
                        const controlsModule = await import('https://cdn.jsdelivr.net/npm/three@0.170.0/examples/jsm/controls/OrbitControls.js/+esm');
                        this.OrbitControls = controlsModule.OrbitControls;
                        this.threeLoaded = true;
                    },

                    async loadVersion(index) {
                        this.currentIndex = index;
                        this.loading = true;
                        this.error = null;

                        try {
                            await this.ensureThreeLoaded();
                            const THREE = this.THREE;

                            const response = await fetch(this.versions[index].jsonUrl);
                            if (!response.ok) throw new Error('Impossible de charger le fichier 3D');
                            const jsonData = await response.json();

                            // Cleanup previous scene
                            if (this.animationId) cancelAnimationFrame(this.animationId);
                            if (this.renderer) {
                                this.renderer.dispose();
                                this.renderer.domElement.remove();
                            }

                            const container = this.$refs.viewer;
                            const width = container.clientWidth;
                            const height = container.clientHeight;

                            // Scene setup
                            const scene = new THREE.Scene();
                            scene.background = new THREE.Color(0xfafafb);

                            const camera = new THREE.PerspectiveCamera(45, width / height, 0.1, 10000);
                            const renderer = new THREE.WebGLRenderer({ antialias: true });
                            renderer.setSize(width, height);
                            renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));
                            renderer.toneMapping = THREE.ACESFilmicToneMapping;
                            renderer.toneMappingExposure = 1.2;
                            container.appendChild(renderer.domElement);

                            const controls = new this.OrbitControls(camera, renderer.domElement);
                            controls.enableDamping = true;
                            controls.dampingFactor = 0.08;

                            // Lighting
                            scene.add(new THREE.AmbientLight(0xffffff, 0.6));
                            const dirLight = new THREE.DirectionalLight(0xffffff, 1.2);
                            dirLight.position.set(5, 10, 7);
                            scene.add(dirLight);
                            const fillLight = new THREE.DirectionalLight(0xffffff, 0.4);
                            fillLight.position.set(-5, 3, -5);
                            scene.add(fillLight);

                            // Build mesh from JSON
                            const geometry = this.buildGeometry(THREE, jsonData);
                            const material = new THREE.MeshStandardMaterial({
                                color: 0x8a8a8a,
                                metalness: 0.85,
                                roughness: 0.55,
                                side: THREE.DoubleSide,
                            });
                            const mesh = new THREE.Mesh(geometry, material);
                            scene.add(mesh);

                            // Edges
                            const edgesGeometry = new THREE.EdgesGeometry(geometry, 30);
                            const edgesMaterial = new THREE.LineBasicMaterial({ color: 0x333333, linewidth: 1 });
                            scene.add(new THREE.LineSegments(edgesGeometry, edgesMaterial));

                            // Fit camera
                            const box = new THREE.Box3().setFromObject(mesh);
                            const center = box.getCenter(new THREE.Vector3());
                            const size = box.getSize(new THREE.Vector3());
                            const maxDim = Math.max(size.x, size.y, size.z);
                            const distance = maxDim * 2;

                            camera.position.set(center.x + distance * 0.6, center.y + distance * 0.4, center.z + distance * 0.7);
                            camera.lookAt(center);
                            controls.target.copy(center);
                            controls.update();

                            this.scene = scene;
                            this.camera = camera;
                            this.renderer = renderer;
                            this.controls = controls;

                            // Animation loop
                            const animate = () => {
                                this.animationId = requestAnimationFrame(animate);
                                controls.update();
                                renderer.render(scene, camera);
                            };
                            animate();

                            // Resize handler
                            const resizeObserver = new ResizeObserver(() => {
                                const w = container.clientWidth;
                                const h = container.clientHeight;
                                camera.aspect = w / h;
                                camera.updateProjectionMatrix();
                                renderer.setSize(w, h);
                            });
                            resizeObserver.observe(container);

                            this.loading = false;
                        } catch (e) {
                            console.error('[AdminViewer]', e);
                            this.error = e.message || 'Erreur lors du chargement';
                            this.loading = false;
                        }
                    },

                    buildGeometry(THREE, jsonData) {
                        const positions = [];

                        // Onshape format
                        if (jsonData.faces?.bodies) {
                            for (const body of jsonData.faces.bodies) {
                                for (const face of (body.faces || [])) {
                                    for (const facet of (face.facets || [])) {
                                        const verts = facet.vertices;
                                        if (verts && verts.length >= 9) {
                                            for (let i = 0; i < verts.length - 8; i += 9) {
                                                positions.push(
                                                    verts[i], verts[i+1], verts[i+2],
                                                    verts[i+3], verts[i+4], verts[i+5],
                                                    verts[i+6], verts[i+7], verts[i+8]
                                                );
                                            }
                                        }
                                    }
                                }
                            }
                        }
                        // FreeCad format
                        else if (jsonData.objects) {
                            for (const obj of jsonData.objects) {
                                const verts = obj.vertices || [];
                                const facets = obj.facets || [];
                                for (const f of facets) {
                                    const indices = f.vertices || f;
                                    if (indices.length >= 3) {
                                        for (let i = 0; i < 3; i++) {
                                            const idx = indices[i] * 3;
                                            positions.push(verts[idx], verts[idx+1], verts[idx+2]);
                                        }
                                    }
                                }
                            }
                        }

                        const geometry = new THREE.BufferGeometry();
                        geometry.setAttribute('position', new THREE.Float32BufferAttribute(positions, 3));
                        geometry.computeVertexNormals();
                        return geometry;
                    },

                    destroy() {
                        if (this.animationId) cancelAnimationFrame(this.animationId);
                        if (this.renderer) this.renderer.dispose();
                    }
                }));
            });
        </script>
    @endif

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
                                <div class="prose prose-sm dark:prose-invert max-w-none text-zinc-700 dark:text-zinc-300 prose-p:my-1 prose-ul:my-2 prose-ol:my-2 prose-li:my-0.5 prose-pre:my-2 prose-code:text-violet-700 prose-code:bg-violet-50 prose-code:px-1 prose-code:py-0.5 prose-code:rounded dark:prose-code:bg-violet-500/10 dark:prose-code:text-violet-300">
                                    {!! \Illuminate\Support\Str::markdown($message->message, [
                                        'html_input' => 'strip',
                                        'allow_unsafe_links' => false,
                                    ]) !!}
                                </div>
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

                            {{-- Generated Files --}}
                            @if($message->ai_cad_path || $message->ai_step_path || $message->ai_technical_drawing_path || $message->ai_json_edge_path)
                                <div class="pt-3 border-t border-zinc-100 dark:border-zinc-800">
                                    <p class="text-xs font-medium text-zinc-500 dark:text-zinc-400 mb-2">Fichiers générés</p>
                                    <div class="flex flex-wrap gap-2">
                                        @if($message->ai_step_path)
                                            <flux:badge color="green" size="sm">
                                                <flux:icon.document-arrow-down class="size-3.5" />
                                                STEP
                                            </flux:badge>
                                        @endif
                                        @if($message->ai_cad_path)
                                            <flux:badge color="blue" size="sm">
                                                <flux:icon.cube class="size-3.5" />
                                                OBJ
                                            </flux:badge>
                                        @endif
                                        @if($message->ai_technical_drawing_path)
                                            <flux:badge color="purple" size="sm">
                                                <flux:icon.document class="size-3.5" />
                                                PDF
                                            </flux:badge>
                                        @endif
                                        @if($message->ai_json_edge_path)
                                            <flux:badge color="zinc" size="sm">
                                                <flux:icon.code-bracket class="size-3.5" />
                                                JSON
                                            </flux:badge>
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
