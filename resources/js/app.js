// resources/js/app.js (simplified JSON viewer)
import * as THREE from "three";
import { OrbitControls } from "three/addons/controls/OrbitControls.js";

// --- Helper: Calculate mesh volume using signed tetrahedron method ---
function calculateMeshVolume(geometry) {
    if (!geometry || !geometry.attributes?.position) return 0;

    const pos = geometry.attributes.position;
    const index = geometry.index;
    let volume = 0;

    if (index) {
        // Indexed geometry
        for (let i = 0; i < index.count; i += 3) {
            const i0 = index.getX(i);
            const i1 = index.getX(i + 1);
            const i2 = index.getX(i + 2);

            const v0 = new THREE.Vector3(
                pos.getX(i0),
                pos.getY(i0),
                pos.getZ(i0),
            );
            const v1 = new THREE.Vector3(
                pos.getX(i1),
                pos.getY(i1),
                pos.getZ(i1),
            );
            const v2 = new THREE.Vector3(
                pos.getX(i2),
                pos.getY(i2),
                pos.getZ(i2),
            );

            // Signed volume of tetrahedron formed with origin
            volume += v0.dot(v1.clone().cross(v2)) / 6;
        }
    } else {
        // Non-indexed geometry
        for (let i = 0; i < pos.count; i += 3) {
            const v0 = new THREE.Vector3(pos.getX(i), pos.getY(i), pos.getZ(i));
            const v1 = new THREE.Vector3(
                pos.getX(i + 1),
                pos.getY(i + 1),
                pos.getZ(i + 1),
            );
            const v2 = new THREE.Vector3(
                pos.getX(i + 2),
                pos.getY(i + 2),
                pos.getZ(i + 2),
            );

            volume += v0.dot(v1.clone().cross(v2)) / 6;
        }
    }

    return Math.abs(volume); // Return absolute value (mm³)
}

// --- Minimal viewer class ---
class JsonModelViewer3D {
    constructor(containerId = "viewer") {
        console.log(
            "[JsonModelViewer3D] Constructor called with containerId:",
            containerId,
        );
        this.container = document.getElementById(containerId);
        console.log("[JsonModelViewer3D] Container found:", this.container);
        if (!this.container) {
            console.error(
                "[JsonModelViewer3D] container not found:",
                containerId,
            );
            return;
        }

        // three
        this.scene = new THREE.Scene();

        // --- lights : plus lumineux, plus diffus ---
        this.scene.background = new THREE.Color(0xfafafb); // blanc doux

        const w = this.container.clientWidth || 800;
        const h = this.container.clientHeight || 600;
        this.camera = new THREE.PerspectiveCamera(45, w / h, 0.1, 5000);
        this.camera.position.set(0.8, 0.8, 1.6);

        this.renderer = new THREE.WebGLRenderer({ antialias: true });
        // --- renderer plus clair ---
        this.renderer.outputColorSpace = THREE.SRGBColorSpace;
        this.renderer.toneMapping = THREE.ACESFilmicToneMapping;
        this.renderer.toneMappingExposure = 1.4; // équilibré pour voir les détails
        this.renderer.setPixelRatio(window.devicePixelRatio);
        this.renderer.setSize(w, h);
        this.container.innerHTML = "";
        this.container.appendChild(this.renderer.domElement);

        // --- Lumières pour métaux : ambiance + reflets ---
        this.scene.background = new THREE.Color(0xfcfcfc); // gris très clair

        // Lumière d'ambiance douce (simule le ciel)
        const ambient = new THREE.AmbientLight(0xffffff, 0.4);
        this.scene.add(ambient);

        // Hemisphere pour environnement studio
        this.scene.add(new THREE.HemisphereLight(0xffffff, 0xe0e0e0, 0.5));

        // Lumière principale (key light) - crée les reflets principaux
        const key = new THREE.DirectionalLight(0xffffff, 1.8);
        key.position.set(4, 5, 3);
        this.scene.add(key);

        // Fill light - adoucit les ombres côté gauche
        const fill = new THREE.DirectionalLight(0xffffff, 0.6);
        fill.position.set(-4, 3, 2);
        this.scene.add(fill);

        // Rim light - crée des reflets sur les arêtes (essentiel pour les métaux)
        const rim = new THREE.DirectionalLight(0xffffff, 0.8);
        rim.position.set(-3, 4, -3);
        this.scene.add(rim);

        // Top light - simule l'éclairage de studio
        const top = new THREE.DirectionalLight(0xffffff, 0.5);
        top.position.set(0, 5, 0);
        this.scene.add(top);

        // controls
        this.controls = new OrbitControls(
            this.camera,
            this.renderer.domElement,
        );
        this.controls.enableDamping = true;

        // picking
        this.raycaster = new THREE.Raycaster();
        this.pointer = new THREE.Vector2();

        // model
        this.modelGroup = new THREE.Group();
        this.scene.add(this.modelGroup);
        this.mesh = null;

        // Features semantic data from FreeCad JSON
        this.features = null; // Will store array of features with type, subtype, diameter, etc.

        // edges (contours)
        this.edgesLine = null;
        this.edgesVisible = false;
        this.edgeThreshold = 45;
        this.edgesColor = "#000000";

        // measure tool (2-clicks)
        this.measureMode = false;
        this.measurePoints = [];
        this.measurePreviewPoint = null;
        this.measureLine = null;
        this.measureMaterial = new THREE.LineBasicMaterial({ color: 0x7c3aed });
        this.measureLabelEl = null;

        // material tri-state : rendu acier C45 usiné CNC (mat, sombre)
        const cncNormal = this.createNormalMap("cnc");
        this.materialBase = new THREE.MeshPhysicalMaterial({
            color: "#4a4f54", // gris acier anthracite
            metalness: 1,
            roughness: 0.55, // mat/brut
            clearcoat: 0.05, // quasi pas de vernis
            clearcoatRoughness: 0,
            reflectivity: 0.7,
            side: THREE.DoubleSide,
            normalMap: cncNormal,
            normalScale: new THREE.Vector2(0.6, 0.6),
        });
        this.materialHover = this.materialBase.clone();
        this.materialHover.color.set("#2d6cff");
        this.materialHover.normalMap = cncNormal;
        this.materialHover.normalScale = new THREE.Vector2(0.6, 0.6);
        this.materialSelect = this.materialBase.clone();
        this.materialSelect.color.set("#ff3b3b");
        this.materialSelect.normalMap = cncNormal;
        this.materialSelect.normalScale = new THREE.Vector2(0.6, 0.6);
        this.selectedGroupIndices = []; // Array for multi-face feature selection (oblongs, etc.)
        this.hoveredGroupIndex = null;

        // Crée une envMap simple pour les reflets (gradient ciel/sol)
        this.setupEnvironmentMap();

        // events
        window.addEventListener("resize", () => this.onResize());
        this.renderer.domElement.addEventListener("mousemove", (e) =>
            this.onPointerMove(e),
        );
        this.renderer.domElement.addEventListener("mouseup", (e) =>
            this.onMouseUp(e),
        );
        this._down = { x: 0, y: 0 };
        this.renderer.domElement.addEventListener("mousedown", (e) => {
            this._down.x = e.clientX;
            this._down.y = e.clientY;
        });

        // Navigation Cube - créer le canvas dynamiquement
        this.navigationCube = null;
        const navCubeCanvas = document.createElement("canvas");
        navCubeCanvas.id = "navigation-cube";
        navCubeCanvas.width = 150;
        navCubeCanvas.height = 150;
        navCubeCanvas.style.cssText =
            "position: absolute; top: 16px; right: 16px; width: 150px; height: 150px; pointer-events: auto; z-index: 50; cursor: pointer; border-radius: 0.5rem; box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);";
        this.container.appendChild(navCubeCanvas);
        console.log(
            "[JsonModelViewer3D] Navigation cube canvas created and appended:",
            navCubeCanvas,
        );

        try {
            this.navigationCube = new NavigationCube(
                navCubeCanvas,
                this.camera,
                this.controls,
            );
            console.log("[JsonModelViewer3D] Navigation cube initialized");
        } catch (error) {
            console.error(
                "[JsonModelViewer3D] Failed to initialize navigation cube:",
                error,
            );
        }

        this.animate();

        // Force initial resize after DOM is ready to ensure canvas takes full container width
        setTimeout(() => {
            this.onResize();
        }, 100);
    }

    // --- Environment Map pour reflets réalistes ---
    setupEnvironmentMap() {
        // Crée une envMap plus complexe simulant un studio photo
        const size = 1024; // Plus haute résolution pour meilleurs reflets
        const canvas = document.createElement("canvas");
        canvas.width = size;
        canvas.height = size;
        const ctx = canvas.getContext("2d");

        // Fond dégradé studio : blanc lumineux -> gris neutre
        const bgGradient = ctx.createLinearGradient(0, 0, 0, size);
        bgGradient.addColorStop(0, "#ffffff"); // ciel très lumineux
        bgGradient.addColorStop(0.3, "#f0f0f2"); // haut clair
        bgGradient.addColorStop(0.6, "#d8d8dc"); // milieu
        bgGradient.addColorStop(1, "#c0c0c8"); // sol gris

        ctx.fillStyle = bgGradient;
        ctx.fillRect(0, 0, size, size);

        // Ajoute des zones lumineuses (simule des softbox de studio)
        ctx.globalCompositeOperation = "lighten";

        // Softbox haut gauche
        const grd1 = ctx.createRadialGradient(
            size * 0.2,
            size * 0.2,
            0,
            size * 0.2,
            size * 0.2,
            size * 0.4,
        );
        grd1.addColorStop(0, "rgba(255, 255, 255, 0.8)");
        grd1.addColorStop(1, "rgba(255, 255, 255, 0)");
        ctx.fillStyle = grd1;
        ctx.fillRect(0, 0, size, size);

        // Softbox haut droit
        const grd2 = ctx.createRadialGradient(
            size * 0.8,
            size * 0.25,
            0,
            size * 0.8,
            size * 0.25,
            size * 0.35,
        );
        grd2.addColorStop(0, "rgba(255, 255, 255, 0.7)");
        grd2.addColorStop(1, "rgba(255, 255, 255, 0)");
        ctx.fillStyle = grd2;
        ctx.fillRect(0, 0, size, size);

        ctx.globalCompositeOperation = "source-over";

        const texture = new THREE.CanvasTexture(canvas);
        texture.mapping = THREE.EquirectangularReflectionMapping;

        // Applique à la scène et aux matériaux
        this.scene.environment = texture;
        this.materialBase.envMap = texture;
        this.materialBase.envMapIntensity = 1.0; // réduit pour acier mat
        this.materialHover.envMap = texture;
        this.materialHover.envMapIntensity = 1.0;
        this.materialSelect.envMap = texture;
        this.materialSelect.envMapIntensity = 1.0;
    }

    // --- Création de NormalMaps procédurales pour chaque matériau ---
    createNormalMap(type) {
        const size = 512;
        const canvas = document.createElement("canvas");
        canvas.width = size;
        canvas.height = size;
        const ctx = canvas.getContext("2d");
        const imageData = ctx.createImageData(size, size);
        const data = imageData.data;

        // Base neutre (normal map neutre = RGB(128, 128, 255))
        for (let i = 0; i < data.length; i += 4) {
            data[i] = 128; // R
            data[i + 1] = 128; // G
            data[i + 2] = 255; // B
            data[i + 3] = 255; // A
        }

        if (type === "acier") {
            // Acier : grain aléatoire (aspect brut/industriel)
            for (let y = 0; y < size; y++) {
                for (let x = 0; x < size; x++) {
                    const i = (y * size + x) * 4;
                    const noise = Math.random() * 20 - 10; // ±10
                    data[i] = Math.max(0, Math.min(255, 128 + noise));
                    data[i + 1] = Math.max(0, Math.min(255, 128 + noise * 0.5));
                }
            }
        } else if (type === "inox") {
            // Inox : lignes de brossage horizontales améliorées (aspect brossé linéaire)
            for (let y = 0; y < size; y++) {
                // Bruit de ligne pour irrégularités
                const lineVariation = (Math.random() - 0.5) * 6;
                for (let x = 0; x < size; x++) {
                    const i = (y * size + x) * 4;

                    // Ligne horizontale principale
                    const brushLine = Math.sin(y * 0.8 + x * 0.02) * 8;

                    // Micro-rayures horizontales
                    const microScratch = Math.sin(x * 0.15) * 4;

                    // Bruit fin pour texture
                    const finnoise = (Math.random() - 0.5) * 3;

                    // Combine les effets
                    const totalX =
                        brushLine + microScratch + lineVariation + finnoise;
                    const totalY = -Math.abs(brushLine) * 0.6; // Crée des creux dans les lignes

                    data[i] = Math.max(0, Math.min(255, 128 + totalX));
                    data[i + 1] = Math.max(0, Math.min(255, 128 + totalY));
                }
            }
        } else if (type === "aluminium") {
            // Aluminium : très légères lignes circulaires (aspect poli/usiné)
            const centerX = size / 2;
            const centerY = size / 2;
            for (let y = 0; y < size; y++) {
                for (let x = 0; x < size; x++) {
                    const i = (y * size + x) * 4;
                    const dx = x - centerX;
                    const dy = y - centerY;
                    const dist = Math.sqrt(dx * dx + dy * dy);
                    const circular = Math.sin(dist * 0.2) * 2;
                    const noise = Math.random() * 4 - 2;
                    data[i] = Math.max(
                        0,
                        Math.min(255, 128 + circular + noise),
                    );
                    data[i + 1] = Math.max(
                        0,
                        Math.min(255, 128 - circular * 0.5 + noise * 0.5),
                    );
                }
            }
        } else if (type === "cnc") {
            // CNC : stries circulaires concentriques prononcées (fraisage CNC acier C45)
            const centerX = size / 2;
            const centerY = size / 2;
            for (let y = 0; y < size; y++) {
                for (let x = 0; x < size; x++) {
                    const i = (y * size + x) * 4;
                    const dx = x - centerX;
                    const dy = y - centerY;
                    const dist = Math.sqrt(dx * dx + dy * dy);

                    // Stries circulaires concentriques plus marquées
                    const circularMain = Math.sin(dist * 0.35) * 12;
                    // Variation secondaire pour irrégularités
                    const circularSecond =
                        Math.sin(dist * 0.7 + Math.random() * 0.5) * 4;
                    // Micro-bruit pour grain acier
                    const microNoise = (Math.random() - 0.5) * 8;

                    // Direction tangentielle pour les stries
                    const angle = Math.atan2(dy, dx);
                    const tangentX = -Math.sin(angle);
                    const tangentY = Math.cos(angle);

                    const totalEffect =
                        circularMain + circularSecond + microNoise;

                    data[i] = Math.max(
                        0,
                        Math.min(255, 128 + totalEffect * tangentX * 0.8),
                    );
                    data[i + 1] = Math.max(
                        0,
                        Math.min(255, 128 + totalEffect * tangentY * 0.8),
                    );
                }
            }
        }

        ctx.putImageData(imageData, 0, 0);

        const texture = new THREE.CanvasTexture(canvas);
        texture.wrapS = THREE.RepeatWrapping;
        texture.wrapT = THREE.RepeatWrapping;
        texture.repeat.set(4, 4); // Répète 4x pour plus de détails
        texture.needsUpdate = true;

        return texture;
    }

    // --- Public API ---
    async loadFromPath(jsonPath) {
        try {
            const res = await fetch(jsonPath);
            if (!res.ok) throw new Error(`HTTP ${res.status}`);
            const json = await res.json();
            this.loadJsonData(json);
        } catch (e) {
            console.error("[JsonModelViewer3D] loadFromPath failed:", e);
        }
    }

    loadJsonData(json) {
        // cleanup
        while (this.modelGroup.children.length) {
            const c = this.modelGroup.children.pop();
            c.geometry?.dispose();
            Array.isArray(c.material)
                ? c.material.forEach((m) => m?.dispose())
                : c.material?.dispose();
            this.modelGroup.remove(c);
        }
        this.mesh = null;
        this.selectedGroupIndices = [];
        this.hoveredGroupIndex = null;

        // Store features semantic data if available (from FreeCad JSON)
        if (Array.isArray(json?.features)) {
            this.features = json.features;
            console.log(
                `[JsonModelViewer3D] Loaded ${this.features.length} semantic features from JSON`,
            );
        } else {
            this.features = null;
        }

        // build
        let mesh = null;
        if (json?.faces?.bodies) {
            mesh = this.buildMeshFromOnshapeJson(json);
        } else if (Array.isArray(json?.objects)) {
            mesh = this.buildMeshFromFreecadJson(json);
        }
        if (!mesh) {
            console.warn("[JsonModelViewer3D] unsupported/empty JSON");
            return;
        }

        // assign tri-state materials
        mesh.material = [
            this.materialBase,
            this.materialHover,
            this.materialSelect,
        ];
        if (Array.isArray(mesh.geometry.groups))
            mesh.geometry.groups.forEach((g) => (g.materialIndex = 0));

        this.mesh = mesh;
        this.modelGroup.add(mesh);

        // build edges once and respect current toggle
        this.buildEdges();
        if (this.edgesLine) this.edgesLine.visible = this.edgesVisible;

        // dispatch global dimensions (mm)
        const box = new THREE.Box3().setFromObject(this.modelGroup);
        const size = new THREE.Vector3();
        box.getSize(size);

        // Calculate volume (mm³) and detect thickness
        const volume = mesh ? calculateMeshVolume(mesh.geometry) : 0;
        const dims = [size.x, size.y, size.z].sort((a, b) => a - b);

        // Try to extract thickness from bending features (more accurate for bent parts)
        // Thickness = outer.radius - inner.radius
        let thickness = null;
        if (this.features) {
            const bendingFeature = this.features.find(
                (f) =>
                    f.type === "bending" &&
                    f.inner?.radius !== undefined &&
                    f.outer?.radius !== undefined,
            );
            if (bendingFeature) {
                thickness =
                    bendingFeature.outer.radius - bendingFeature.inner.radius;
                console.log(
                    `[JsonModelViewer3D] Thickness extracted from bending feature: ${thickness}mm`,
                );
            }
        }
        // Fallback: smallest bounding box dimension (works for flat plates)
        if (thickness === null) {
            thickness = dims[0];
        }

        const detail = {
            sizeX: size.x,
            sizeY: size.y,
            sizeZ: size.z,
            unit: "mm",
            volume: volume, // mm³
            thickness: thickness, // mm (detected from smallest dimension)
        };
        window.dispatchEvent(new CustomEvent("cad-model-stats", { detail }));

        this.fitCamera();

        // Ensure canvas is properly sized after model load
        this.onResize();

        // Capture et envoie automatiquement un screenshot après chargement si il n'existe pas déjà
        // Délai de 500ms pour s'assurer que le rendu est stable
        const screenshotExists =
            this.container.getAttribute("data-screenshot-exists") === "true";
        if (!screenshotExists) {
            setTimeout(() => {
                this.captureAndSendScreenshot();
            }, 500);
        } else {
            console.log(
                "[JsonModelViewer3D] Screenshot already exists, skipping capture",
            );
        }
    }

    // --- Edges / Contours ---
    buildEdges() {
        if (this.edgesLine) {
            this.scene.remove(this.edgesLine);
            this.edgesLine.geometry?.dispose();
            this.edgesLine.material?.dispose();
            this.edgesLine = null;
        }
        if (!this.mesh) return;
        const geo = new THREE.EdgesGeometry(
            this.mesh.geometry,
            this.edgeThreshold,
        );
        const mat = new THREE.LineBasicMaterial({ color: this.edgesColor });
        this.edgesLine = new THREE.LineSegments(geo, mat);
        this.scene.add(this.edgesLine);
    }

    toggleEdges(show, threshold = null, color = null) {
        this.edgesVisible = !!show;
        if (typeof threshold === "number") this.edgeThreshold = threshold;
        if (typeof color === "string") this.edgesColor = color;
        this.buildEdges();
        if (this.edgesLine) this.edgesLine.visible = this.edgesVisible;
    }

    // --- Measure helpers ---
    ensureMeasureLabel() {
        if (this.measureLabelEl) return;
        const el = document.createElement("div");
        Object.assign(el.style, {
            position: "absolute",
            padding: "4px 8px",
            background: "rgba(124, 58, 237, 0.95)",
            color: "#fff",
            borderRadius: "6px",
            fontSize: "12px",
            fontWeight: "500",
            pointerEvents: "none",
            transform: "translate(-50%, -120%)",
            boxShadow: "0 2px 8px rgba(0,0,0,0.15)",
        });
        this.container.appendChild(el);
        this.measureLabelEl = el;
    }

    worldToScreen(p) {
        const v = p.clone().project(this.camera);
        return {
            x: (v.x * 0.5 + 0.5) * this.container.clientWidth,
            y: (-v.y * 0.5 + 0.5) * this.container.clientHeight,
        };
    }

    setMeasureMode(enabled) {
        this.measureMode = !!enabled;
        if (!enabled) this.resetMeasure();
        this.renderer.domElement.style.cursor = enabled
            ? "crosshair"
            : "default";
    }

    resetMeasure() {
        this.measurePoints = [];
        this.measurePreviewPoint = null;
        if (this.measureLine) {
            this.scene.remove(this.measureLine);
            this.measureLine.geometry?.dispose();
            this.measureLine = null;
        }
        if (this.measureLabelEl) {
            this.measureLabelEl.remove();
            this.measureLabelEl = null;
        }
    }

    updateMeasureVisual() {
        if (!this.measureMode) return;
        const p1 = this.measurePoints[0] || null;
        const p2 =
            this.measurePoints.length >= 2
                ? this.measurePoints[1]
                : this.measurePreviewPoint || null;
        if (!p1 || !p2) {
            if (this.measureLine) {
                this.scene.remove(this.measureLine);
                this.measureLine.geometry?.dispose();
                this.measureLine = null;
            }
            if (this.measureLabelEl) {
                this.measureLabelEl.style.display = "none";
            }
            return;
        }
        const geo = new THREE.BufferGeometry();
        geo.setAttribute(
            "position",
            new THREE.Float32BufferAttribute(
                [p1.x, p1.y, p1.z, p2.x, p2.y, p2.z],
                3,
            ),
        );
        if (!this.measureLine) {
            this.measureLine = new THREE.Line(geo, this.measureMaterial);
            this.scene.add(this.measureLine);
        } else {
            this.measureLine.geometry.dispose();
            this.measureLine.geometry = geo;
        }
        this.ensureMeasureLabel();
        const mid = p1.clone().add(p2).multiplyScalar(0.5);
        const s = this.worldToScreen(mid);
        const distMM = p1.distanceTo(p2);
        this.measureLabelEl.textContent = `${distMM.toFixed(2)} mm`;
        this.measureLabelEl.style.left = `${s.x}px`;
        this.measureLabelEl.style.top = `${s.y}px`;
        this.measureLabelEl.style.display = "block";
    }

    resetView(fill = 0.92) {
        this.fitCamera(fill);
        this.requestRender();
    }

    // --- Builders ---
    buildMeshFromOnshapeJson(json) {
        const bodies = json?.faces?.bodies;
        if (!Array.isArray(bodies)) return null;

        const pos = []; // non-indexed positions (x,y,z repeated)
        const groups = [];
        const faceGroups = [];
        const realFaceIds = [];

        for (let b = 0; b < bodies.length; b++) {
            const body = bodies[b];
            const faces = body?.faces || [];
            for (let f = 0; f < faces.length; f++) {
                const face = faces[f];
                const startBefore = pos.length;
                const faceId =
                    face.id != null ? String(face.id) : `body${b}_face${f}`;

                const facets = face?.facets || [];
                for (let k = 0; k < facets.length; k++) {
                    const vtx = facets[k]?.vertices;
                    if (!Array.isArray(vtx) || vtx.length < 3) continue;
                    if (vtx.length === 3) {
                        // already a triangle
                        pos.push(
                            vtx[0].x,
                            vtx[0].y,
                            vtx[0].z,
                            vtx[1].x,
                            vtx[1].y,
                            vtx[1].z,
                            vtx[2].x,
                            vtx[2].y,
                            vtx[2].z,
                        );
                    } else {
                        // fan triangulation
                        for (let i = 2; i < vtx.length; i++) {
                            const a = vtx[0],
                                b = vtx[i - 1],
                                c = vtx[i];
                            pos.push(
                                a.x,
                                a.y,
                                a.z,
                                b.x,
                                b.y,
                                b.z,
                                c.x,
                                c.y,
                                c.z,
                            );
                        }
                    }
                }

                const addedFloats = pos.length - startBefore;
                const startIndex = startBefore / 3;
                const countIndex = addedFloats / 3;
                if (countIndex > 0) {
                    groups.push({ start: startIndex, count: countIndex });
                    faceGroups.push({
                        start: startIndex,
                        count: countIndex,
                        id: faceId,
                    });
                    realFaceIds.push(faceId);
                }
            }
        }

        if (!pos.length || !groups.length) return null;

        const geometry = new THREE.BufferGeometry();
        geometry.setAttribute(
            "position",
            new THREE.Float32BufferAttribute(pos, 3),
        );
        geometry.computeVertexNormals();
        geometry.groups = groups;

        const mesh = new THREE.Mesh(geometry, [
            this.materialBase,
            this.materialHover,
            this.materialSelect,
        ]);
        mesh.userData.faceGroups = faceGroups;
        mesh.userData.realFaceIdsByGroup = realFaceIds;
        return mesh;
    }

    buildMeshFromFreecadJson(json) {
        const objects = json?.objects;
        if (!Array.isArray(objects)) return null;

        const positions = [];
        const groups = [];
        const faceGroups = [];
        const realFaceIds = [];
        let baseVertex = 0;

        for (let oi = 0; oi < objects.length; oi++) {
            const obj = objects[oi];
            const verts = obj?.vertices || [];
            const facets = obj?.facets || [];
            for (let v = 0; v < verts.length; v++)
                positions.push(verts[v][0], verts[v][1], verts[v][2]);

            for (let fi = 0; fi < facets.length; fi++) {
                const face = facets[fi];
                if (!Array.isArray(face) || face.length < 3) continue;
                const start = positions.length / 3;
                // build triangles into a temporary array and then map to non-indexed
                const triIndices = [];
                triIndices.push(
                    baseVertex + face[0],
                    baseVertex + face[1],
                    baseVertex + face[2],
                );
                for (let k = 3; k < face.length; k++)
                    triIndices.push(
                        baseVertex + face[0],
                        baseVertex + face[k - 1],
                        baseVertex + face[k],
                    );

                // expand to non-indexed positions
                const tmp = [];
                for (let i = 0; i < triIndices.length; i++) {
                    const vi = triIndices[i];
                    const vx = json.objects[oi].vertices[vi - baseVertex];
                    tmp.push(vx[0], vx[1], vx[2]);
                }
                const added = tmp.length / 3;
                // append tmp to positions end
                for (let i = 0; i < tmp.length; i++) positions.push(tmp[i]);

                groups.push({ start, count: added });
                const id = `freecad_obj${oi}_facet${fi}`;
                faceGroups.push({ start, count: added, id });
                realFaceIds.push(id);
            }
            baseVertex += verts.length;
        }

        if (!positions.length || !groups.length) return null;

        const geometry = new THREE.BufferGeometry();
        geometry.setAttribute(
            "position",
            new THREE.Float32BufferAttribute(positions, 3),
        );
        geometry.computeVertexNormals();
        geometry.groups = groups;

        const mesh = new THREE.Mesh(geometry, [
            this.materialBase,
            this.materialHover,
            this.materialSelect,
        ]);
        mesh.userData.faceGroups = faceGroups;
        mesh.userData.realFaceIdsByGroup = realFaceIds;
        return mesh;
    }

    // --- Helpers ---
    fitCamera(fill = 0.92) {
        // fill = fraction de l'écran à occuper (0..1)
        const box = new THREE.Box3().setFromObject(this.modelGroup);
        const size = new THREE.Vector3();
        const center = new THREE.Vector3();
        box.getSize(size);
        box.getCenter(center);

        // garde-fous
        const eps = 1e-6;
        size.x = Math.max(size.x, eps);
        size.y = Math.max(size.y, eps);
        size.z = Math.max(size.z, eps);

        // distance requise pour cadrer en vertical et horizontal
        const vFov = THREE.MathUtils.degToRad(this.camera.fov);
        const hFov = 2 * Math.atan(Math.tan(vFov / 2) * this.camera.aspect);
        const distV = (size.y * 0.5) / Math.tan(vFov / 2);
        const distH = (size.x * 0.5) / Math.tan(hFov / 2);
        const dist = Math.max(distV, distH);

        // marge contrôlée par 'fill' (0.92 ≈ occuper 92% du viewport)
        const targetDist = dist / Math.max(0.05, Math.min(0.98, fill));

        // point de vue isométrique propre
        const dir = new THREE.Vector3(1, 1, 1).normalize();
        this.camera.position.copy(center).add(dir.multiplyScalar(targetDist));
        this.camera.lookAt(center);

        // plage de clipping stable
        this.camera.near = Math.max(0.01, targetDist * 0.02);
        this.camera.far = targetDist * 50;
        this.camera.updateProjectionMatrix();

        this.controls.target.copy(center);
        this.controls.update();
    }

    onResize() {
        if (!this.container) return;
        const w = this.container.clientWidth || 800;
        const h = this.container.clientHeight || 600;
        this.camera.aspect = w / h;
        this.camera.updateProjectionMatrix();
        this.renderer.setSize(w, h);
    }

    onPointerMove(e) {
        const rect = this.renderer.domElement.getBoundingClientRect();
        this.pointer.x = ((e.clientX - rect.left) / rect.width) * 2 - 1;
        this.pointer.y = -((e.clientY - rect.top) / rect.height) * 2 + 1;

        if (!this.mesh) return;
        this.raycaster.setFromCamera(this.pointer, this.camera);
        const hits = this.raycaster.intersectObject(this.mesh, false);

        if (this.measureMode && this.measurePoints.length === 1) {
            this.measurePreviewPoint =
                hits.length > 0 ? hits[0].point.clone() : null;
            this.updateMeasureVisual();
        }

        let newHover = null;
        if (
            hits[0]?.faceIndex != null &&
            Array.isArray(this.mesh.geometry.groups)
        ) {
            const triIndex = hits[0].faceIndex;
            for (let gi = 0; gi < this.mesh.geometry.groups.length; gi++) {
                const g = this.mesh.geometry.groups[gi];
                const triStart = g.start / 3;
                const triEnd = (g.start + g.count) / 3;
                if (triIndex >= triStart && triIndex < triEnd) {
                    newHover = gi;
                    break;
                }
            }
        }

        if (newHover !== this.hoveredGroupIndex) {
            this.hoveredGroupIndex = newHover;
            this.updateMaterialStates();
        }
    }

    onMouseUp(e) {
        const wasDrag =
            Math.abs(e.clientX - this._down.x) > 5 ||
            Math.abs(e.clientY - this._down.y) > 5;
        if (wasDrag) return;

        if (this.measureMode) {
            this.raycaster.setFromCamera(this.pointer, this.camera);
            const hits = this.raycaster.intersectObject(this.mesh, false);
            if (hits.length > 0) {
                const p = hits[0].point.clone();
                if (this.measurePoints.length < 2) this.measurePoints.push(p);
                else this.measurePoints = [p];
                this.updateMeasureVisual();
            }
            return;
        }

        if (!this.mesh) return;
        this.raycaster.setFromCamera(this.pointer, this.camera);
        const hits = this.raycaster.intersectObject(this.mesh, false);
        if (hits.length === 0) {
            this.selectedGroupIndices = [];
            this.updateMaterialStates();
            // inform Livewire (clear)
            Livewire?.dispatch?.("chatObjectClick", { objectId: null });
            Livewire?.dispatch?.("chatObjectClickReal", { objectId: null });
            window.Alpine?.dispatchEvent?.("cad-selection", null) ||
                window.dispatchEvent(
                    new CustomEvent("cad-selection", { detail: null }),
                );
            return;
        }

        const hit = hits[0];
        // resolve group from triangle index (non-indexed)
        let groupIdx = null;
        if (hit.faceIndex != null && Array.isArray(this.mesh.geometry.groups)) {
            const triIndex = hit.faceIndex;
            for (let gi = 0; gi < this.mesh.geometry.groups.length; gi++) {
                const g = this.mesh.geometry.groups[gi];
                const triStart = g.start / 3;
                const triEnd = (g.start + g.count) / 3;
                if (triIndex >= triStart && triIndex < triEnd) {
                    groupIdx = gi;
                    break;
                }
            }
        }

        const fg = this.mesh.userData?.faceGroups?.[groupIdx];
        const faceId = fg?.id ?? groupIdx;
        const realId =
            this.mesh.userData?.realFaceIdsByGroup?.[groupIdx] ?? faceId;

        // Try to find semantic feature data from FreeCad JSON first
        const featureData = this.getFeatureForFaceId(realId);
        console.log("🔎 Feature lookup:", {
            realId,
            featureData,
            featuresCount: this.features?.length,
        });

        // For multi-face features (oblongs, fillets, chamfers, bending, etc.), select ALL faces of the feature
        // Count all face/edge IDs including nested structures for bending
        const faceIdsCount = Array.isArray(featureData?.face_ids)
            ? featureData.face_ids.length
            : 0;
        const edgeIdsCount = Array.isArray(featureData?.edge_ids)
            ? featureData.edge_ids.length
            : 0;
        // Bending features have nested inner/outer face_ids
        const innerFaceIdsCount = Array.isArray(featureData?.inner?.face_ids)
            ? featureData.inner.face_ids.length
            : 0;
        const outerFaceIdsCount = Array.isArray(featureData?.outer?.face_ids)
            ? featureData.outer.face_ids.length
            : 0;
        const totalIds =
            faceIdsCount + edgeIdsCount + innerFaceIdsCount + outerFaceIdsCount;

        if (featureData && totalIds > 1) {
            this.selectedGroupIndices =
                this.getGroupIndicesForFeature(featureData);
            console.log("🔗 Multi-face feature selection:", {
                type: featureData.type,
                faceIds: featureData.face_ids,
                edgeIds: featureData.edge_ids,
                innerFaceIds: featureData.inner?.face_ids,
                outerFaceIds: featureData.outer?.face_ids,
                selectedIndices: this.selectedGroupIndices,
            });
        } else {
            // Single face selection
            this.selectedGroupIndices = groupIdx !== null ? [groupIdx] : [];
        }
        this.updateMaterialStates();

        // Note: L'ancien système Livewire (chatObjectClick) est remplacé par
        // le FaceSelectionManager qui gère les chips et l'injection de contexte

        // Compute combined metrics for all selected faces
        const pos = this.mesh.geometry.getAttribute("position");
        let sx = 0,
            sy = 0,
            sz = 0,
            n = 0;
        let minX = Infinity,
            minY = Infinity,
            minZ = Infinity;
        let maxX = -Infinity,
            maxY = -Infinity,
            maxZ = -Infinity;
        let areaMm2 = 0;
        const vertices = [];
        const a = new THREE.Vector3(),
            b = new THREE.Vector3(),
            c = new THREE.Vector3();
        const ab = new THREE.Vector3(),
            ac = new THREE.Vector3();

        // Gather data from all selected face groups
        const selectedFaceGroups = this.selectedGroupIndices
            .map((idx) => this.mesh.userData?.faceGroups?.[idx])
            .filter(Boolean);

        for (const fgItem of selectedFaceGroups) {
            for (let i = fgItem.start; i < fgItem.start + fgItem.count; i++) {
                const x = pos.getX(i),
                    y = pos.getY(i),
                    z = pos.getZ(i);
                sx += x;
                sy += y;
                sz += z;
                n++;
                if (x < minX) minX = x;
                if (y < minY) minY = y;
                if (z < minZ) minZ = z;
                if (x > maxX) maxX = x;
                if (y > maxY) maxY = y;
                if (z > maxZ) maxZ = z;
                vertices.push(new THREE.Vector3(x, y, z));
            }
            // area sum over triangles
            for (
                let i = fgItem.start;
                i < fgItem.start + fgItem.count;
                i += 3
            ) {
                a.set(pos.getX(i), pos.getY(i), pos.getZ(i));
                b.set(pos.getX(i + 1), pos.getY(i + 1), pos.getZ(i + 1));
                c.set(pos.getX(i + 2), pos.getY(i + 2), pos.getZ(i + 2));
                ab.subVectors(b, a);
                ac.subVectors(c, a);
                areaMm2 += ab.cross(ac).length() * 0.5;
            }
        }
        const centroid = { x: sx / n, y: sy / n, z: sz / n };

        // Determine face type: prefer FreeCad semantic data over geometric detection
        let faceType, metrics;
        if (featureData) {
            // Use semantic type from FreeCad
            faceType = featureData.type; // "hole", "countersink", "fillet", "chamfer", "slot"

            // Override faceType to 'thread' for threaded holes
            if (featureData.type === "hole") {
                const isThreaded =
                    featureData.subtype === "threaded" ||
                    featureData.subtype === "tapped" ||
                    (featureData.thread !== null &&
                        featureData.thread !== undefined &&
                        featureData.thread !== "");
                if (isThreaded) {
                    faceType = "thread";
                }
            }

            // Build metrics from feature data
            metrics = {
                displayType: this.getFeatureDisplayType(featureData),
                ...featureData, // Include all feature properties (diameter, depth, position, etc.)
            };
        } else {
            // Fallback to geometric detection
            faceType = this.detectFaceType(fg, this.mesh.geometry);
            const bbox = {
                min: new THREE.Vector3(minX, minY, minZ),
                max: new THREE.Vector3(maxX, maxY, maxZ),
            };
            metrics = this.computeFaceMetrics(
                faceType,
                vertices,
                bbox,
                areaMm2,
            );
        }

        const detail = {
            id: faceId,
            realFaceId: realId,
            faceType: faceType, // NOUVEAU
            metrics: metrics, // NOUVEAU
            centroid,
            triangles: Math.floor(fg.count / 3),
            unit: "mm",
            bbox: {
                x: maxX - minX,
                y: maxY - minY,
                z: maxZ - minZ,
            },
            area: +areaMm2.toFixed(2), // mm²
            feature: featureData, // Semantic feature data from FreeCad (type, subtype, diameter, thread, etc.)
        };
        window.Alpine?.dispatchEvent?.("cad-selection", detail) ||
            window.dispatchEvent(new CustomEvent("cad-selection", { detail }));
    }

    /**
     * Find group index for a given face ID
     * @param {string} faceId - The face ID to search for
     * @returns {number|null} - The group index if found, null otherwise
     */
    getGroupIndexForFaceId(faceId) {
        if (!this.mesh?.userData?.realFaceIdsByGroup) return null;
        const realIds = this.mesh.userData.realFaceIdsByGroup;
        for (let i = 0; i < realIds.length; i++) {
            if (realIds[i] === faceId) return i;
        }
        return null;
    }

    /**
     * Get all group indices for a feature (for multi-face features like oblongs, fillets, bending)
     * @param {object} feature - The feature object with face_ids or edge_ids array
     * @returns {number[]} - Array of group indices
     */
    getGroupIndicesForFeature(feature) {
        if (!feature) return [];
        const indices = [];

        // Support face_ids (for oblongs, holes) and edge_ids (for fillets, chamfers)
        // Also support nested structures for bending (inner.face_ids, outer.face_ids)
        const allIds = [
            ...(Array.isArray(feature.face_ids) ? feature.face_ids : []),
            ...(Array.isArray(feature.edge_ids) ? feature.edge_ids : []),
            // Bending features have nested inner/outer face_ids
            ...(feature.inner && Array.isArray(feature.inner.face_ids)
                ? feature.inner.face_ids
                : []),
            ...(feature.outer && Array.isArray(feature.outer.face_ids)
                ? feature.outer.face_ids
                : []),
        ];

        for (const faceId of allIds) {
            const idx = this.getGroupIndexForFaceId(faceId);
            if (idx !== null) indices.push(idx);
        }
        return indices;
    }

    /**
     * Find feature data for a given face ID from the loaded FreeCad features
     * @param {string} faceId - The face ID to search for (e.g., "JfG", "JfA")
     * @returns {object|null} - The feature object if found, null otherwise
     */
    getFeatureForFaceId(faceId) {
        if (!this.features || !Array.isArray(this.features)) {
            return null;
        }

        for (const feature of this.features) {
            // Check root level face_ids
            if (
                Array.isArray(feature.face_ids) &&
                feature.face_ids.includes(faceId)
            ) {
                return feature;
            }
            // Check root level edge_ids
            if (
                Array.isArray(feature.edge_ids) &&
                feature.edge_ids.includes(faceId)
            ) {
                return feature;
            }
            // Check nested structures for bending features (inner.face_ids, outer.face_ids)
            if (
                feature.inner &&
                Array.isArray(feature.inner.face_ids) &&
                feature.inner.face_ids.includes(faceId)
            ) {
                return feature;
            }
            if (
                feature.outer &&
                Array.isArray(feature.outer.face_ids) &&
                feature.outer.face_ids.includes(faceId)
            ) {
                return feature;
            }
        }

        return null;
    }

    /**
     * Get human-readable display type for a feature
     * @param {object} feature - Feature object from FreeCad JSON
     * @returns {string} - Display name
     */
    getFeatureDisplayType(feature) {
        if (!feature || !feature.type) return "Feature";

        // Special handling for holes - include thread info if available
        if (feature.type === "hole") {
            // Check both subtype and thread property to detect threaded holes
            // Thread property contains the designation (e.g., "M3", "M4") or null
            const isThreaded =
                feature.subtype === "threaded" ||
                feature.subtype === "tapped" ||
                (feature.thread !== null &&
                    feature.thread !== undefined &&
                    feature.thread !== "");

            if (isThreaded) {
                // Build thread designation (e.g., "M3", "M4", "M5")
                let threadInfo = "Taraudage";
                if (feature.thread) {
                    // If thread property exists with a value (e.g., "M3", "M4")
                    threadInfo += ` ${feature.thread}`;
                } else if (feature.diameter) {
                    // Infer metric thread from diameter (M3 = 3mm, M4 = 4mm, etc.)
                    const d = parseFloat(feature.diameter);
                    if (!isNaN(d)) {
                        // Standard metric thread diameters
                        const standardThreads = [
                            2, 2.5, 3, 4, 5, 6, 8, 10, 12, 14, 16, 18, 20,
                        ];
                        const closest = standardThreads.reduce((prev, curr) =>
                            Math.abs(curr - d) < Math.abs(prev - d)
                                ? curr
                                : prev,
                        );
                        if (Math.abs(closest - d) < 0.5) {
                            threadInfo += ` M${closest}`;
                        }
                    }
                }
                return threadInfo;
            }
            return "Perçage";
        }

        // Special handling for fillets - include radius if available
        if (feature.type === "fillet") {
            if (feature.radius !== undefined && feature.radius !== null) {
                return `Congé R${feature.radius}`;
            }
            return "Congé";
        }

        // Special handling for bending - include radius info if available
        if (feature.type === "bending") {
            const innerRadius = feature.inner?.radius;
            const outerRadius = feature.outer?.radius;
            if (innerRadius !== undefined && innerRadius !== null) {
                return `Pliage R${innerRadius}`;
            }
            return "Pliage";
        }

        const typeMap = {
            countersink: "Fraisage",
            chamfer: "Chanfrein",
            slot: "Rainure",
            box: "Face", // Face plane (from FreeCad API)
            oblong: "Oblong", // Oblong hole (slot with rounded ends)
            rectangular: "Face rectangulaire", // Rectangular face
            square: "Face carrée", // Square face
        };

        return typeMap[feature.type] || feature.type;
    }

    /**

   * Since JSON doesn't provide type metadata, we analyze vertices, normals, and bounding box
   */
    detectFaceType(faceGroup, geometry) {
        const pos = geometry.getAttribute("position");
        const vertices = [];

        // Extract all vertices for this face
        for (
            let i = faceGroup.start;
            i < faceGroup.start + faceGroup.count;
            i++
        ) {
            vertices.push(
                new THREE.Vector3(pos.getX(i), pos.getY(i), pos.getZ(i)),
            );
        }

        // Compute normals to detect curvature
        const normals = this.computeFaceNormals(vertices);
        const normalVariation = this.computeNormalVariation(normals);

        // Compute bounding box
        const bbox = this.computeBoundingBox(vertices);
        const dimensions = {
            x: bbox.max.x - bbox.min.x,
            y: bbox.max.y - bbox.min.y,
            z: bbox.max.z - bbox.min.z,
        };

        // Sort dimensions to find patterns
        const sorted = [dimensions.x, dimensions.y, dimensions.z].sort(
            (a, b) => a - b,
        );

        // Debug logs
        console.log("🔍 Face Detection Debug:", {
            normalVariation: normalVariation.toFixed(4),
            dimensions: {
                x: dimensions.x.toFixed(2),
                y: dimensions.y.toFixed(2),
                z: dimensions.z.toFixed(2),
            },
            sorted: sorted.map((d) => d.toFixed(2)),
            vertexCount: vertices.length,
            triangleCount: normals.length,
        });

        // Check cylindrical characteristics
        const isCylindrical = normalVariation > 0.1;

        // For cylinders, check if any two dimensions are similar (not necessarily sorted[0] and sorted[1])
        // Check all three combinations
        const diff01 = Math.abs(sorted[0] - sorted[1]);
        const diff12 = Math.abs(sorted[1] - sorted[2]);
        const diff02 = Math.abs(sorted[0] - sorted[2]);
        const maxDiff = Math.max(diff01, diff12, diff02);
        const minDiff = Math.min(diff01, diff12, diff02);

        // Two dimensions are similar if the smallest difference is much smaller than the largest
        const twoSimilarDims = minDiff < sorted[2] * 0.3; // 30% tolerance

        // Additional check: for very small features, be more lenient
        const isVerySmall = sorted[2] < 20;

        console.log("🎯 Detection criteria:", {
            isCylindrical,
            twoSimilarDims,
            diff01: diff01.toFixed(2),
            diff12: diff12.toFixed(2),
            diff02: diff02.toFixed(2),
            minDiff: minDiff.toFixed(2),
            maxDim: sorted[2].toFixed(2),
            isSmall: sorted[2] < 50,
            isVerySmall,
        });

        // For cylindrical faces, determine if it's a hole or fillet by checking angular span
        if (isCylindrical && twoSimilarDims) {
            const angularSpan = this.computeAngularSpan(vertices, bbox);
            console.log("🔄 Angular span:", angularSpan.toFixed(1) + "°");

            // Fillet: typically ~90° arc (quarter cylinder on an edge)
            // Hole: typically 180°-360° arc (half or full cylinder)
            const isFillet = angularSpan < 135;

            if (isFillet) {
                console.log("✅ Detected: fillet (angular span < 135°)");
                return "fillet";
            }

            // Small cylindrical face with large angular span = hole
            if (sorted[2] < 50) {
                const hasThreadPattern = this.detectThreadPattern(vertices);
                console.log(
                    "✅ Detected:",
                    hasThreadPattern ? "thread" : "hole",
                );
                return hasThreadPattern ? "thread" : "hole";
            }

            console.log("✅ Detected: cylindrical");
            return "cylindrical";
        }

        // Planar face (flat surface)
        if (normalVariation < 0.05) {
            console.log("✅ Detected: planar (low variation)");
            return "planar";
        }

        console.log("✅ Detected: planar (default)");
        return "planar"; // Default
    }

    computeFaceNormals(vertices) {
        const normals = [];
        for (let i = 0; i < vertices.length - 2; i += 3) {
            const a = vertices[i];
            const b = vertices[i + 1];
            const c = vertices[i + 2];

            const ab = new THREE.Vector3().subVectors(b, a);
            const ac = new THREE.Vector3().subVectors(c, a);
            const normal = new THREE.Vector3().crossVectors(ab, ac).normalize();

            normals.push(normal);
        }
        return normals;
    }

    computeNormalVariation(normals) {
        if (normals.length === 0) return 0;

        const avgNormal = new THREE.Vector3();
        normals.forEach((n) => avgNormal.add(n));
        avgNormal.divideScalar(normals.length).normalize();

        let totalDeviation = 0;
        normals.forEach((n) => {
            const angle = Math.acos(
                Math.max(-1, Math.min(1, n.dot(avgNormal))),
            );
            totalDeviation += angle;
        });

        return totalDeviation / normals.length;
    }

    computeBoundingBox(vertices) {
        const bbox = {
            min: new THREE.Vector3(Infinity, Infinity, Infinity),
            max: new THREE.Vector3(-Infinity, -Infinity, -Infinity),
        };

        vertices.forEach((v) => {
            bbox.min.x = Math.min(bbox.min.x, v.x);
            bbox.min.y = Math.min(bbox.min.y, v.y);
            bbox.min.z = Math.min(bbox.min.z, v.z);
            bbox.max.x = Math.max(bbox.max.x, v.x);
            bbox.max.y = Math.max(bbox.max.y, v.y);
            bbox.max.z = Math.max(bbox.max.z, v.z);
        });

        return bbox;
    }

    computeAngularSpan(vertices, bbox) {
        const center = new THREE.Vector3(
            (bbox.min.x + bbox.max.x) / 2,
            (bbox.min.y + bbox.max.y) / 2,
            (bbox.min.z + bbox.max.z) / 2,
        );

        const dims = {
            x: bbox.max.x - bbox.min.x,
            y: bbox.max.y - bbox.min.y,
            z: bbox.max.z - bbox.min.z,
        };
        const sorted = [dims.x, dims.y, dims.z].sort((a, b) => a - b);

        let axisIndex = 0;
        if (sorted[2] === dims.y) axisIndex = 1;
        else if (sorted[2] === dims.z) axisIndex = 2;

        const angles = vertices.map((v) => {
            let dx, dy;
            if (axisIndex === 0) {
                dy = v.y - center.y;
                dx = v.z - center.z;
            } else if (axisIndex === 1) {
                dy = v.x - center.x;
                dx = v.z - center.z;
            } else {
                dy = v.x - center.x;
                dx = v.y - center.y;
            }
            return Math.atan2(dy, dx);
        });

        const minAngle = Math.min(...angles);
        const maxAngle = Math.max(...angles);
        let span = (maxAngle - minAngle) * (180 / Math.PI);

        if (span < 0) span += 360;
        if (span > 360) span = 360;

        return span;
    }

    detectThreadPattern(vertices) {
        if (vertices.length < 100) return false;

        const bbox = this.computeBoundingBox(vertices);
        const center = new THREE.Vector3(
            (bbox.min.x + bbox.max.x) / 2,
            (bbox.min.y + bbox.max.y) / 2,
            (bbox.min.z + bbox.max.z) / 2,
        );

        const angles = vertices
            .map((v) => {
                const dx = v.x - center.x;
                const dy = v.y - center.y;
                return Math.atan2(dy, dx);
            })
            .sort((a, b) => a - b);

        const diffs = [];
        for (let i = 1; i < angles.length; i++) {
            diffs.push(angles[i] - angles[i - 1]);
        }

        const avgDiff = diffs.reduce((a, b) => a + b, 0) / diffs.length;
        const variance =
            diffs.reduce((sum, d) => sum + Math.pow(d - avgDiff, 2), 0) /
            diffs.length;

        return variance < 0.01; // Regular pattern = thread
    }

    computeFaceMetrics(faceType, vertices, bbox, area) {
        const dimensions = {
            x: bbox.max.x - bbox.min.x,
            y: bbox.max.y - bbox.min.y,
            z: bbox.max.z - bbox.min.z,
        };

        const sorted = [dimensions.x, dimensions.y, dimensions.z].sort(
            (a, b) => a - b,
        );
        const centroid = {
            x: (bbox.min.x + bbox.max.x) / 2,
            y: (bbox.min.y + bbox.max.y) / 2,
            z: (bbox.min.z + bbox.max.z) / 2,
        };

        switch (faceType) {
            case "planar":
                return {
                    displayType: "Face plane",
                    length: sorted[2],
                    width: sorted[1],
                    thickness: sorted[0],
                    area: area,
                    centroid: centroid,
                };

            case "fillet":
                return {
                    displayType: "Congé",
                    radius: sorted[0] / 2,
                    length: sorted[2],
                    area: area,
                    centroid: centroid,
                };

            case "cylindrical":
                return {
                    displayType: "Cylindre",
                    radius: sorted[0] / 2,
                    diameter: sorted[0],
                    depth: sorted[2],
                    length: sorted[2],
                    area: area,
                    centroid: centroid,
                };

            case "hole":
            case "thread":
                // For holes/threads, find the two similar dimensions (diameter) vs the outlier (depth)
                // Compare differences between consecutive sorted values
                const diff01 = Math.abs(sorted[1] - sorted[0]);
                const diff12 = Math.abs(sorted[2] - sorted[1]);

                let holeDiameter, holeDepth;
                if (diff01 < diff12) {
                    // sorted[0] and sorted[1] are similar -> they represent the diameter
                    holeDiameter = (sorted[0] + sorted[1]) / 2;
                    holeDepth = sorted[2];
                } else {
                    // sorted[1] and sorted[2] are similar -> they represent the diameter
                    holeDiameter = (sorted[1] + sorted[2]) / 2;
                    holeDepth = sorted[0];
                }

                return {
                    displayType:
                        faceType === "thread" ? "Taraudage" : "Perçage",
                    diameter: holeDiameter,
                    depth: holeDepth,
                    pitch: faceType === "thread" ? null : undefined,
                    position: centroid,
                    area: area,
                };

            default:
                return {
                    displayType: "Face",
                    area: area,
                    centroid: centroid,
                };
        }
    }

    updateMaterialStates() {
        if (!this.mesh?.geometry?.groups) return;
        // Reset all groups to base material
        for (const g of this.mesh.geometry.groups) g.materialIndex = 0;

        // Apply selection material to all selected groups (for multi-face features)
        if (this.selectedGroupIndices.length > 0) {
            for (const idx of this.selectedGroupIndices) {
                if (this.mesh.geometry.groups[idx]) {
                    this.mesh.geometry.groups[idx].materialIndex = 2;
                }
            }
        } else if (this.hoveredGroupIndex != null) {
            // Only apply hover if nothing is selected
            this.mesh.geometry.groups[this.hoveredGroupIndex].materialIndex = 1;
        }

        this.mesh.material = [
            this.materialBase,
            this.materialHover,
            this.materialSelect,
        ];
        this.mesh.material.needsUpdate = true;
        this.requestRender();
    }

    requestRender() {
        this._dirty = true;
    }

    /**
     * Capture un screenshot de la scène 3D actuelle
     * @param {number} width - Largeur du screenshot (défaut: 800)
     * @param {number} height - Hauteur du screenshot (défaut: 800)
     * @returns {Promise<Blob>} - Promise résolvant avec le blob de l'image PNG
     */
    async captureScreenshot(width = 800, height = 800) {
        return new Promise((resolve, reject) => {
            try {
                // Sauvegarde de la taille actuelle
                const originalWidth = this.container.clientWidth;
                const originalHeight = this.container.clientHeight;
                const originalAspect = this.camera.aspect;

                // Redimensionne temporairement le renderer
                this.camera.aspect = width / height;
                this.camera.updateProjectionMatrix();
                this.renderer.setSize(width, height);

                // Force un rendu
                this.renderer.render(this.scene, this.camera);

                // Capture le screenshot
                this.renderer.domElement.toBlob(
                    (blob) => {
                        // Restaure la taille originale
                        this.camera.aspect = originalAspect;
                        this.camera.updateProjectionMatrix();
                        this.renderer.setSize(originalWidth, originalHeight);
                        this.renderer.render(this.scene, this.camera);

                        if (blob) {
                            resolve(blob);
                        } else {
                            reject(
                                new Error("Failed to create blob from canvas"),
                            );
                        }
                    },
                    "image/png",
                    0.95,
                );
            } catch (error) {
                console.error(
                    "[JsonModelViewer3D] Screenshot capture failed:",
                    error,
                );
                reject(error);
            }
        });
    }

    /**
     * Capture et envoie un screenshot au serveur Livewire
     */
    async captureAndSendScreenshot() {
        try {
            console.log("[JsonModelViewer3D] Capturing screenshot...");
            const blob = await this.captureScreenshot(800, 800);

            // Convertit le blob en base64
            const reader = new FileReader();
            reader.onloadend = () => {
                const base64 = reader.result.split(",")[1]; // Retire le préfixe data:image/png;base64,
                console.log(
                    "[JsonModelViewer3D] Screenshot captured, size:",
                    blob.size,
                    "bytes",
                );

                // Envoie à Livewire
                if (window.Livewire) {
                    Livewire.dispatch("saveClientScreenshot", {
                        base64Data: base64,
                    });
                    console.log(
                        "[JsonModelViewer3D] Screenshot sent to Livewire",
                    );
                } else {
                    console.warn(
                        "[JsonModelViewer3D] Livewire not available, screenshot not sent",
                    );
                }
            };
            reader.onerror = (error) => {
                console.error(
                    "[JsonModelViewer3D] Failed to read blob:",
                    error,
                );
            };
            reader.readAsDataURL(blob);
        } catch (error) {
            console.error(
                "[JsonModelViewer3D] Failed to capture and send screenshot:",
                error,
            );
        }
    }

    animate() {
        requestAnimationFrame(() => this.animate());
        this.controls?.update();
        this.navigationCube?.update(); // Synchroniser le cube de navigation
        if (this._dirty) {
            this.renderer.render(this.scene, this.camera);
            this._dirty = false;
        } else {
            this.renderer.render(this.scene, this.camera);
        }
    }
}

class FaceSelectionManager {
    constructor() {
        this.selections = new Map();
        this.autoClearAfterSend = true;
        this.container = null;
        this.textarea = null;
        this.initialized = false;
        this.setupListeners();
    }

    /**
     * Trouve les éléments DOM nécessaires (appelé à chaque utilisation car Livewire peut les recréer)
     */
    findElements() {
        this.container =
            document.querySelector("[data-face-selection-chips]") ||
            document.getElementById("face-selection-chips");

        // Le flux:composer génère un textarea interne
        const composer = document.querySelector("[data-flux-composer]");
        if (composer) {
            this.textarea = composer.querySelector("textarea");
        }

        // Fallback: chercher directement
        if (!this.textarea) {
            this.textarea =
                document.querySelector(
                    'form[wire\\:submit\\.prevent="send"] textarea',
                ) || document.getElementById("message");
        }
    }

    setupListeners() {
        // Écoute les sélections de faces depuis le viewer 3D
        window.addEventListener("cad-selection", (event) => {
            const detail = event?.detail ?? null;
            if (detail && detail.id !== undefined) {
                this.handleFaceSelectionDetail(detail);
            } else if (detail === null) {
                // Désélection - effacer toutes les pastilles
                this.clearSelections();
            }
        });

        // Intercepte le submit du formulaire AVANT Livewire
        // C'est la méthode la plus fiable pour injecter le contexte
        document.addEventListener(
            "submit",
            (e) => {
                const form = e.target;
                if (
                    form?.matches &&
                    form.matches('form[wire\\:submit\\.prevent="send"]')
                ) {
                    if (this.selections.size > 0) {
                        this.injectContextIntoTextarea();
                        // Schedule clear après envoi
                        if (this.autoClearAfterSend) {
                            setTimeout(() => this.clearSelections(), 500);
                        }
                    }
                }
            },
            { capture: true },
        );

        // Écoute aussi l'événement keydown Enter sur le composer
        document.addEventListener(
            "keydown",
            (e) => {
                if (e.key === "Enter" && !e.shiftKey) {
                    const composer = e.target.closest("[data-flux-composer]");
                    if (composer && this.selections.size > 0) {
                        this.injectContextIntoTextarea();
                        // Schedule clear après envoi
                        if (this.autoClearAfterSend) {
                            setTimeout(() => this.clearSelections(), 500);
                        }
                    }
                }
            },
            { capture: true },
        );
    }

    handleFaceSelectionDetail(detail) {
        if (!detail) return;
        this.findElements();

        const faceId = detail.realFaceId ?? detail.id ?? null;
        if (faceId === null) return;

        const context = detail.context || this.buildFallbackContext(detail);
        const summary = detail.summary || this.buildSummary(detail);
        const label =
            detail.realFaceId || detail.id
                ? `Face ${detail.realFaceId ?? detail.id}`
                : "Face";

        // Efface les anciennes sélections pour n'en garder qu'une seule à la fois
        this.selections.clear();

        // Ajoute la nouvelle sélection
        this.selections.set(faceId, {
            context,
            summary,
            label,
            detail, // Garde le détail complet pour référence
        });

        this.renderChips();

        // Focus sur le textarea
        if (this.textarea) {
            this.textarea.focus();
        }

        console.log(
            `[FaceSelectionManager] Face selected: ${faceId}`,
            this.selections.size,
            "selections",
        );
    }

    buildFallbackContext(detail) {
        const centroid = detail.centroid || {};
        const bbox = detail.bbox || {};
        const area = detail.area;

        let ctx = `Face Selection: ID[${detail.realFaceId ?? detail.id}]`;
        ctx += ` Position[center(${this.toNumber(centroid.x)}, ${this.toNumber(centroid.y)}, ${this.toNumber(centroid.z)})]`;
        ctx += ` BBox[Size(${this.toNumber(bbox.x)}, ${this.toNumber(bbox.y)}, ${this.toNumber(bbox.z)})]`;
        if (area) {
            ctx += ` Area[${this.toNumber(area)} mm²]`;
        }
        return ctx;
    }

    buildSummary(detail) {
        const parts = [];
        if (detail.summary) parts.push(detail.summary);
        if (detail.bbox) {
            const { x, y, z } = detail.bbox;
            parts.push(
                `${this.toNumber(x)}×${this.toNumber(y)}×${this.toNumber(z)} mm`,
            );
        }
        if (detail.area) {
            parts.push(`~${this.toNumber(detail.area)} mm²`);
        }
        return parts.join(" • ");
    }

    renderChips() {
        this.findElements();
        if (!this.container) {
            console.warn("[FaceSelectionManager] Chips container not found");
            return;
        }

        this.container.innerHTML = "";

        if (this.selections.size === 0) {
            this.container.classList.add("hidden");
            window.dispatchEvent(
                new CustomEvent("face-selection-changed", {
                    detail: { hasSelection: false },
                }),
            );
            return;
        }

        this.container.classList.remove("hidden");

        for (const [id, selection] of this.selections.entries()) {
            const chip = document.createElement("div");
            chip.className =
                "inline-flex items-center gap-2 px-3 py-1.5 rounded-full border border-violet-200 bg-violet-50 text-violet-700 text-sm font-medium shadow-sm cursor-default transition-all hover:bg-violet-100 dark:border-violet-700 dark:bg-violet-900/40 dark:text-violet-200 dark:hover:bg-violet-900/60";
            chip.title = selection.summary || selection.context;

            // Icône cube
            const icon = document.createElement("span");
            icon.innerHTML = `<svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor"><path d="M11 17a1 1 0 001.447.894l4-2A1 1 0 0017 15V9.236a1 1 0 00-1.447-.894l-4 2a1 1 0 00-.553.894V17zM15.211 6.276a1 1 0 000-1.788l-4.764-2.382a1 1 0 00-.894 0L4.789 4.488a1 1 0 000 1.788l4.764 2.382a1 1 0 00.894 0l4.764-2.382zM4.447 8.342A1 1 0 003 9.236V15a1 1 0 00.553.894l4 2A1 1 0 009 17v-5.764a1 1 0 00-.553-.894l-4-2z"/></svg>`;
            chip.appendChild(icon);

            // Label
            const label = document.createElement("span");
            label.textContent = selection.label ?? `Face ${id}`;
            chip.appendChild(label);

            // Bouton de suppression
            const removeBtn = document.createElement("button");
            removeBtn.type = "button";
            removeBtn.className =
                "ml-1 w-4 h-4 flex items-center justify-center rounded-full text-violet-400 hover:text-violet-600 hover:bg-violet-200 transition-colors";
            removeBtn.innerHTML = `<svg xmlns="http://www.w3.org/2000/svg" class="w-3 h-3" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/></svg>`;
            removeBtn.addEventListener("click", (e) => {
                e.preventDefault();
                e.stopPropagation();
                this.selections.delete(id);
                this.renderChips();
                console.log(`[FaceSelectionManager] Face removed: ${id}`);
            });
            chip.appendChild(removeBtn);

            this.container.appendChild(chip);
        }

        window.dispatchEvent(
            new CustomEvent("face-selection-changed", {
                detail: { hasSelection: true },
            }),
        );
    }

    /**
     * Génère la chaîne de contexte à envoyer au moteur
     */
    generateContextString() {
        if (this.selections.size === 0) return "";
        return Array.from(this.selections.values())
            .map((sel) => `[FACE_CONTEXT: ${sel.context}]`)
            .join(" ");
    }

    /**
     * Injecte le contexte directement dans le textarea
     */
    injectContextIntoTextarea() {
        this.findElements();
        if (!this.textarea) {
            console.warn("[FaceSelectionManager] Textarea not found");
            return;
        }

        const ctx = this.generateContextString();
        if (!ctx) return;

        const currentValue = this.textarea.value || "";
        if (currentValue.includes("[FACE_CONTEXT:")) return;

        const newValue = [currentValue.trim(), ctx].filter(Boolean).join(" ");
        this.textarea.value = newValue;

        // Déclenche les événements pour que Livewire détecte le changement
        this.textarea.dispatchEvent(new Event("input", { bubbles: true }));
        this.textarea.dispatchEvent(new Event("change", { bubbles: true }));

        console.log("[FaceSelectionManager] Context injected into textarea");
    }

    /**
     * Efface toutes les sélections
     */
    clearSelections() {
        this.selections.clear();
        this.renderChips();
        console.log("[FaceSelectionManager] Selections cleared");
    }

    /**
     * Retourne les sélections actuelles (pour debug ou API)
     */
    getSelections() {
        return Array.from(this.selections.entries()).map(([id, sel]) => ({
            id,
            ...sel,
        }));
    }

    toNumber(value) {
        const n = Number(value);
        if (Number.isNaN(n)) return "—";
        return Number.isFinite(n) ? n.toFixed(1) : "—";
    }
}

/**
 * Utility to parse face context patterns and generate chip HTML
 */
class FaceContextParser {
    static PATTERN = /\[FACE_CONTEXT:\s*(.+?)\]\]/g;

    static parse(content) {
        if (!content) return content;

        return content.replace(this.PATTERN, (match, faceContext) => {
            console.log("[FaceContextParser] Match found:", match);
            console.log("[FaceContextParser] Captured context:", faceContext);
            return this.createChipHTML(faceContext);
        });
    }

    static createChipHTML(faceContext) {
        // Extract face ID from context (e.g., "Face Selection: ID[JfB] ...")
        const idMatch = faceContext.match(/ID\[([^\]]+)\]/);
        console.log("[FaceContextParser] ID match:", idMatch);
        const faceId = idMatch ? idMatch[1] : "Unknown";
        const label = `Face ${faceId}`;
        console.log("[FaceContextParser] Final label:", label);

        // Create chip HTML identical to composer chips
        return `<span class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full border border-violet-200 bg-violet-50 text-violet-700 text-sm font-medium">
      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
      </svg>
      <span>${label}</span>
    </span>`;
    }
}

// Export for use in Blade templates
window.FaceContextParser = FaceContextParser;

/**
 * NavigationCube - Simple navigation cube
 * Components:
 * - Main cube with 6 labeled faces (FRONT, BACK, LEFT, RIGHT, TOP, BOTTOM)
 * - XYZ axis indicators with colored labels
 */
class NavigationCube {
    constructor(containerElement, mainCamera, mainControls) {
        this.container = containerElement;
        this.mainCamera = mainCamera;
        this.mainControls = mainControls;

        // State
        this.hoveredObject = null;
        this.isAnimating = false;

        // Colors
        this.colors = {
            cubeBase: 0x6b7280,      // Gray
            cubeHover: 0x3b82f6,     // Blue
            text: 0xffffff,          // White text
            axisX: 0xef4444,         // Red
            axisY: 0x22c55e,         // Green
            axisZ: 0x3b82f6          // Blue
        };

        // Setup renderer
        this.renderer = new THREE.WebGLRenderer({
            canvas: containerElement,
            alpha: true,
            antialias: true
        });
        this.renderer.setSize(150, 150);
        this.renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));
        this.renderer.setClearColor(0x000000, 0);

        // Setup scene
        this.scene = new THREE.Scene();

        // Setup camera
        this.camera = new THREE.OrthographicCamera(-2.5, 2.5, 2.5, -2.5, 0.1, 20);
        this.camera.position.set(0, 0, 5);
        this.camera.lookAt(0, 0, 0);

        // Lighting
        this.scene.add(new THREE.AmbientLight(0xffffff, 0.6));
        const dirLight = new THREE.DirectionalLight(0xffffff, 0.4);
        dirLight.position.set(2, 3, 4);
        this.scene.add(dirLight);

        // Create components (cube + axes only)
        this.createCube();
        this.createAxes();

        // Raycaster
        this.raycaster = new THREE.Raycaster();
        this.mouse = new THREE.Vector2();

        // Events
        this.onClick = this.onClick.bind(this);
        this.onMouseMove = this.onMouseMove.bind(this);
        this.onMouseLeave = this.onMouseLeave.bind(this);

        this.container.addEventListener('click', this.onClick);
        this.container.addEventListener('mousemove', this.onMouseMove);
        this.container.addEventListener('mouseleave', this.onMouseLeave);

        this.render();
    }

    createCube() {
        this.cubeGroup = new THREE.Group();
        this.cubeFaces = [];

        const size = 1.0;
        const halfSize = size / 2;

        // Face definitions
        const faces = [
            { name: 'FRONT', pos: [0, 0, halfSize], rot: [0, 0, 0], normal: [0, 0, 1] },
            { name: 'BACK', pos: [0, 0, -halfSize], rot: [0, Math.PI, 0], normal: [0, 0, -1] },
            { name: 'RIGHT', pos: [halfSize, 0, 0], rot: [0, Math.PI/2, 0], normal: [1, 0, 0] },
            { name: 'LEFT', pos: [-halfSize, 0, 0], rot: [0, -Math.PI/2, 0], normal: [-1, 0, 0] },
            { name: 'TOP', pos: [0, halfSize, 0], rot: [-Math.PI/2, 0, 0], normal: [0, 1, 0] },
            { name: 'BOTTOM', pos: [0, -halfSize, 0], rot: [Math.PI/2, 0, 0], normal: [0, -1, 0] }
        ];

        faces.forEach(face => {
            // Create face
            const geometry = new THREE.PlaneGeometry(size * 0.95, size * 0.95);
            const material = new THREE.MeshStandardMaterial({
                color: this.colors.cubeBase,
                side: THREE.DoubleSide,
                metalness: 0.1,
                roughness: 0.8
            });

            const mesh = new THREE.Mesh(geometry, material);
            mesh.position.set(...face.pos);
            mesh.rotation.set(...face.rot);
            mesh.userData = {
                type: 'face',
                name: face.name,
                normal: new THREE.Vector3(...face.normal),
                originalColor: this.colors.cubeBase
            };

            // Add label
            const label = this.createLabel(face.name);
            label.position.z = 0.01;
            mesh.add(label);

            this.cubeGroup.add(mesh);
            this.cubeFaces.push(mesh);
        });

        // Add edges
        const boxGeometry = new THREE.BoxGeometry(size, size, size);
        const edges = new THREE.EdgesGeometry(boxGeometry);
        const edgeMaterial = new THREE.LineBasicMaterial({ color: 0x374151 });
        const edgeLines = new THREE.LineSegments(edges, edgeMaterial);
        this.cubeGroup.add(edgeLines);

        this.scene.add(this.cubeGroup);
    }

    createLabel(text) {
        const canvas = document.createElement('canvas');
        canvas.width = 128;
        canvas.height = 128;
        const ctx = canvas.getContext('2d');

        ctx.clearRect(0, 0, 128, 128);
        ctx.font = 'bold 24px system-ui, sans-serif';
        ctx.fillStyle = '#ffffff';
        ctx.textAlign = 'center';
        ctx.textBaseline = 'middle';
        ctx.fillText(text, 64, 64);

        const texture = new THREE.CanvasTexture(canvas);
        const material = new THREE.SpriteMaterial({ map: texture, transparent: true });
        const sprite = new THREE.Sprite(material);
        sprite.scale.set(0.5, 0.5, 1);

        return sprite;
    }

    createAxes() {
        this.axesGroup = new THREE.Group();
        this.axesGroup.userData.nonInteractive = true;

        const length = 1.6;
        const radius = 0.02;

        // X axis (Red)
        this.createAxis('X', this.colors.axisX, new THREE.Vector3(1, 0, 0), length, radius);
        // Y axis (Green)
        this.createAxis('Y', this.colors.axisY, new THREE.Vector3(0, 1, 0), length, radius);
        // Z axis (Blue)
        this.createAxis('Z', this.colors.axisZ, new THREE.Vector3(0, 0, 1), length, radius);

        this.scene.add(this.axesGroup);
    }

    createAxis(label, color, direction, length, radius) {
        const material = new THREE.MeshBasicMaterial({ color });

        // Line
        const lineGeom = new THREE.CylinderGeometry(radius, radius, length, 8);
        const line = new THREE.Mesh(lineGeom, material);

        if (direction.x === 1) {
            line.rotation.z = -Math.PI / 2;
            line.position.x = length / 2;
        } else if (direction.y === 1) {
            line.position.y = length / 2;
        } else {
            line.rotation.x = Math.PI / 2;
            line.position.z = length / 2;
        }
        this.axesGroup.add(line);

        // Arrow tip
        const coneGeom = new THREE.ConeGeometry(radius * 3, radius * 8, 8);
        const cone = new THREE.Mesh(coneGeom, material);

        if (direction.x === 1) {
            cone.rotation.z = -Math.PI / 2;
            cone.position.x = length + radius * 4;
        } else if (direction.y === 1) {
            cone.position.y = length + radius * 4;
        } else {
            cone.rotation.x = Math.PI / 2;
            cone.position.z = length + radius * 4;
        }
        this.axesGroup.add(cone);

        // Label
        const labelSprite = this.createAxisLabel(label, color);
        if (direction.x === 1) labelSprite.position.x = length + 0.3;
        else if (direction.y === 1) labelSprite.position.y = length + 0.3;
        else labelSprite.position.z = length + 0.3;
        this.axesGroup.add(labelSprite);
    }

    createAxisLabel(text, color) {
        const canvas = document.createElement('canvas');
        canvas.width = 64;
        canvas.height = 64;
        const ctx = canvas.getContext('2d');

        ctx.clearRect(0, 0, 64, 64);
        ctx.font = 'bold 40px system-ui, sans-serif';
        ctx.fillStyle = '#' + color.toString(16).padStart(6, '0');
        ctx.textAlign = 'center';
        ctx.textBaseline = 'middle';
        ctx.fillText(text, 32, 32);

        const texture = new THREE.CanvasTexture(canvas);
        const material = new THREE.SpriteMaterial({ map: texture, transparent: true });
        const sprite = new THREE.Sprite(material);
        sprite.scale.set(0.25, 0.25, 1);
        sprite.userData.nonInteractive = true;

        return sprite;
    }

    onMouseMove(event) {
        const rect = this.container.getBoundingClientRect();
        this.mouse.x = ((event.clientX - rect.left) / rect.width) * 2 - 1;
        this.mouse.y = -((event.clientY - rect.top) / rect.height) * 2 + 1;

        this.raycaster.setFromCamera(this.mouse, this.camera);

        // Reset previous hover
        if (this.hoveredObject) {
            this.hoveredObject.material.color.setHex(this.hoveredObject.userData.originalColor);
            this.hoveredObject = null;
        }

        this.container.style.cursor = 'default';

        // Check faces
        const faceIntersects = this.raycaster.intersectObjects(this.cubeFaces);
        if (faceIntersects.length > 0) {
            this.hoveredObject = faceIntersects[0].object;
            this.hoveredObject.material.color.setHex(this.colors.cubeHover);
            this.container.style.cursor = 'pointer';
        }

        this.render();
    }

    onMouseLeave() {
        if (this.hoveredObject) {
            this.hoveredObject.material.color.setHex(this.hoveredObject.userData.originalColor);
            this.hoveredObject = null;
        }
        this.container.style.cursor = 'default';
        this.render();
    }

    onClick(event) {
        if (this.isAnimating) return;

        const rect = this.container.getBoundingClientRect();
        this.mouse.x = ((event.clientX - rect.left) / rect.width) * 2 - 1;
        this.mouse.y = -((event.clientY - rect.top) / rect.height) * 2 + 1;

        this.raycaster.setFromCamera(this.mouse, this.camera);

        // Check faces
        const faceIntersects = this.raycaster.intersectObjects(this.cubeFaces);
        if (faceIntersects.length > 0) {
            const face = faceIntersects[0].object;
            this.orientToNormal(face.userData.normal);
        }
    }

    orientToNormal(normal) {
        const target = this.mainControls.target.clone();
        const distance = this.mainCamera.position.distanceTo(target);
        const newPos = normal.clone().multiplyScalar(distance).add(target);
        this.animateCameraTo(newPos, target);
    }

    animateCameraTo(position, target) {
        this.isAnimating = true;
        const duration = 400;
        const startPos = this.mainCamera.position.clone();
        const startTarget = this.mainControls.target.clone();
        const startTime = Date.now();

        const animate = () => {
            const t = Math.min((Date.now() - startTime) / duration, 1);
            const eased = 1 - Math.pow(1 - t, 3);

            this.mainCamera.position.lerpVectors(startPos, position, eased);
            this.mainControls.target.lerpVectors(startTarget, target, eased);
            this.mainControls.update();

            if (t < 1) {
                requestAnimationFrame(animate);
            } else {
                this.isAnimating = false;
            }
        };

        animate();
    }

    update() {
        const quaternion = this.mainCamera.quaternion.clone().invert();

        if (this.cubeGroup) this.cubeGroup.quaternion.copy(quaternion);
        if (this.axesGroup) this.axesGroup.quaternion.copy(quaternion);

        this.render();
    }

    render() {
        this.renderer.render(this.scene, this.camera);
    }

    dispose() {
        this.container.removeEventListener('click', this.onClick);
        this.container.removeEventListener('mousemove', this.onMouseMove);
        this.container.removeEventListener('mouseleave', this.onMouseLeave);
        this.renderer.dispose();
    }
}

window.NavigationCube = NavigationCube;

// --- Global wiring ---
let JSON_VIEWER = null;
let FACE_SELECTION_MANAGER = null;

function ensureViewer() {
    if (!JSON_VIEWER) {
        // Only initialize if the viewer container exists
        if (document.getElementById("viewer")) {
            JSON_VIEWER = new JsonModelViewer3D("viewer");
        }
    }
    return JSON_VIEWER;
}

function ensureSelectionManager() {
    if (!FACE_SELECTION_MANAGER) {
        const hasContainer =
            document.querySelector("[data-face-selection-chips]") ||
            document.getElementById("face-selection-chips");
        if (hasContainer) {
            FACE_SELECTION_MANAGER = new FaceSelectionManager();
            window.selectionManager = FACE_SELECTION_MANAGER;
            window.faceSelectionManager = FACE_SELECTION_MANAGER;
        }
    }
    return FACE_SELECTION_MANAGER;
}

// Livewire entry point kept identical: expects { jsonPath }
Livewire.on("jsonEdgesLoaded", ({ jsonPath }) => {
    if (!jsonPath) return;
    const v = ensureViewer();
    v.loadFromPath(jsonPath);
});

// Fit request from panel
// Fonctionne avec $dispatch d'Alpine et window.dispatchEvent natif
window.addEventListener("viewer-fit", () => {
    ensureViewer().resetView();
});
Livewire.on("updatedMaterialPreset", () => {
    // Matériau figé sur le rendu métallique par défaut, aucun changement nécessaire.
});
Livewire.on("toggleMeasureMode", ({ enabled }) => {
    const v = ensureViewer();
    v.setMeasureMode(!!enabled);
});
Livewire.on("resetMeasure", () => {
    const v = ensureViewer();
    v.resetMeasure();
});

document.addEventListener("DOMContentLoaded", () => {
    ensureSelectionManager();
});

// Boot once so the canvas exists even before data arrives (only if container exists)
if (document.getElementById("viewer")) {
    ensureViewer();
}
