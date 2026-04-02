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

                            {{-- 3D Viewer --}}
                            @if($message->ai_json_edge_path)
                                <div class="pt-3">
                                    <p class="text-xs font-medium text-zinc-500 dark:text-zinc-400 mb-2">Visualisation 3D</p>
                                    <div
                                        x-data="adminViewer(@js($message->getJSONEdgeUrl()), @js($chat->material_family?->value ?? 'acier'))"
                                        x-init="init()"
                                        class="relative w-full rounded-lg border border-zinc-200 dark:border-zinc-700 overflow-hidden bg-zinc-50 dark:bg-zinc-900"
                                        style="height: 400px;">
                                        <div x-ref="container" class="w-full h-full"></div>
                                        <div x-show="loading" class="absolute inset-0 flex items-center justify-center bg-zinc-50/80 dark:bg-zinc-900/80">
                                            <flux:icon.arrow-path class="size-6 text-zinc-400 animate-spin" />
                                        </div>
                                        <div x-show="error" class="absolute inset-0 flex items-center justify-center">
                                            <p class="text-sm text-red-500" x-text="error"></p>
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
                                            <a href="{{ $message->getStepUrl() }}" target="_blank" download>
                                                <flux:badge color="green" size="sm" class="cursor-pointer hover:opacity-80 transition-opacity">
                                                    <flux:icon.document-arrow-down class="size-3.5" />
                                                    STEP
                                                </flux:badge>
                                            </a>
                                        @endif
                                        @if($message->ai_cad_path)
                                            <a href="{{ $message->getObjUrl() }}" target="_blank" download>
                                                <flux:badge color="blue" size="sm" class="cursor-pointer hover:opacity-80 transition-opacity">
                                                    <flux:icon.cube class="size-3.5" />
                                                    OBJ
                                                </flux:badge>
                                            </a>
                                        @endif
                                        @if($message->ai_technical_drawing_path)
                                            <a href="{{ $message->getTechnicalDrawingUrl() }}" target="_blank" download>
                                                <flux:badge color="purple" size="sm" class="cursor-pointer hover:opacity-80 transition-opacity">
                                                    <flux:icon.document class="size-3.5" />
                                                    PDF
                                                </flux:badge>
                                            </a>
                                        @endif
                                        @if($message->ai_json_edge_path)
                                            <a href="{{ $message->getJSONEdgeUrl() }}" target="_blank" download>
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
<script type="module">
    import * as THREE from 'https://cdn.jsdelivr.net/npm/three@0.170.0/build/three.module.js';
    import { OrbitControls } from 'https://cdn.jsdelivr.net/npm/three@0.170.0/examples/jsm/controls/OrbitControls.js';

    const MATERIAL_COLORS = {
        acier: { color: 0x8a8a8a, metalness: 0.85, roughness: 0.35 },
        inox: { color: 0xc0c0c0, metalness: 0.95, roughness: 0.15 },
        aluminium: { color: 0xd4d4d8, metalness: 0.9, roughness: 0.2 },
    };

    function buildMesh(json, materialType) {
        const bodies = json?.faces?.bodies;
        if (!Array.isArray(bodies)) return null;

        const positions = [];

        for (const body of bodies) {
            for (const face of (body?.faces || [])) {
                for (const facet of (face?.facets || [])) {
                    const vtx = facet?.vertices;
                    if (!Array.isArray(vtx) || vtx.length < 3) continue;
                    for (let i = 2; i < vtx.length; i++) {
                        positions.push(
                            vtx[0].x, vtx[0].y, vtx[0].z,
                            vtx[i-1].x, vtx[i-1].y, vtx[i-1].z,
                            vtx[i].x, vtx[i].y, vtx[i].z,
                        );
                    }
                }
            }
        }

        if (positions.length === 0) return null;

        const geometry = new THREE.BufferGeometry();
        geometry.setAttribute('position', new THREE.Float32BufferAttribute(positions, 3));
        geometry.computeVertexNormals();

        const matConfig = MATERIAL_COLORS[materialType] || MATERIAL_COLORS.acier;
        const material = new THREE.MeshStandardMaterial({
            color: matConfig.color,
            metalness: matConfig.metalness,
            roughness: matConfig.roughness,
            side: THREE.DoubleSide,
        });

        return new THREE.Mesh(geometry, material);
    }

    Alpine.data('adminViewer', (jsonUrl, materialType) => ({
        loading: true,
        error: null,

        init() {
            const container = this.$refs.container;
            const width = container.clientWidth;
            const height = container.clientHeight;

            const scene = new THREE.Scene();
            scene.background = new THREE.Color(0xfafafb);

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

            // Lighting
            const ambientLight = new THREE.AmbientLight(0xffffff, 0.6);
            scene.add(ambientLight);
            const dirLight = new THREE.DirectionalLight(0xffffff, 1.2);
            dirLight.position.set(5, 10, 7);
            scene.add(dirLight);
            const fillLight = new THREE.DirectionalLight(0xffffff, 0.4);
            fillLight.position.set(-5, 3, -5);
            scene.add(fillLight);

            fetch(jsonUrl)
                .then(r => r.json())
                .then(json => {
                    const mesh = buildMesh(json, materialType);
                    if (!mesh) {
                        this.error = 'Format JSON non reconnu';
                        this.loading = false;
                        return;
                    }

                    scene.add(mesh);

                    // Center and fit camera
                    const box = new THREE.Box3().setFromObject(mesh);
                    const center = box.getCenter(new THREE.Vector3());
                    const size = box.getSize(new THREE.Vector3());
                    const maxDim = Math.max(size.x, size.y, size.z);
                    const distance = maxDim * 2;

                    mesh.position.sub(center);
                    camera.position.set(distance * 0.7, distance * 0.5, distance * 0.7);
                    controls.target.set(0, 0, 0);
                    controls.update();

                    this.loading = false;
                })
                .catch(err => {
                    console.error('[AdminViewer] Error:', err);
                    this.error = 'Erreur de chargement du modèle 3D';
                    this.loading = false;
                });

            // Animation loop
            const animate = () => {
                requestAnimationFrame(animate);
                controls.update();
                renderer.render(scene, camera);
            };
            animate();

            // Resize handling
            const resizeObserver = new ResizeObserver(() => {
                const w = container.clientWidth;
                const h = container.clientHeight;
                camera.aspect = w / h;
                camera.updateProjectionMatrix();
                renderer.setSize(w, h);
            });
            resizeObserver.observe(container);
        }
    }));
</script>
@endscript
