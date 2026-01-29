// resources/js/app.js (simplified JSON viewer)
import * as THREE from "three";
import { OrbitControls } from "three/addons/controls/OrbitControls.js";
import {
    createNormalMap,
    createEnvironmentMap,
    createMaterials,
    setupLighting,
} from "./viewer/materials.js";
import {
    getFeatureForFaceId,
    getFeatureDisplayType,
    getGroupIndexForFaceId,
    getGroupIndicesForFeature,
    detectFaceType,
    computeFaceMetrics,
    computeBoundingBox,
} from "./viewer/feature-detection.js";
import { FaceSelectionManager } from "./selection/FaceSelectionManager.js";
import { FaceContextParser } from "./selection/FaceContextParser.js";
import {
    buildMeshFromOnshapeJson,
    buildMeshFromFreecadJson,
} from "./viewer/mesh-builders.js";
import { calculateMeshVolume } from "./viewer/geometry-utils.js";
import { captureAndSendScreenshot } from "./viewer/screenshot.js";
import { NavigationCube } from "./viewer/navigation-cube.js";

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
        setupLighting(this.scene);

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
        const cncNormal = createNormalMap("cnc");
        const envMap = createEnvironmentMap();
        this.scene.environment = envMap;

        const materials = createMaterials(cncNormal, envMap);
        this.materialBase = materials.base;
        this.materialHover = materials.hover;
        this.materialSelect = materials.select;
        this.selectedGroupIndices = []; // Array for multi-face feature selection (oblongs, etc.)
        this.hoveredGroupIndex = null;

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
        navCubeCanvas.width = 180;
        navCubeCanvas.height = 180;
        navCubeCanvas.style.cssText =
            "position: absolute; top: 16px; right: 16px; width: 180px; height: 180px; pointer-events: auto; z-index: 50; cursor: pointer; border-radius: 0.75rem; box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);";
        this.container.appendChild(navCubeCanvas);

        try {
            this.navigationCube = new NavigationCube(
                navCubeCanvas,
                this.camera,
                this.controls,
            );

            // Hide navigation cube during CAD generation
            window.addEventListener('cad-generation-started', () => {
                if (this.navigationCube) {
                    this.navigationCube.setVisible(false);
                }
            });
            window.addEventListener('cad-generation-ended', () => {
                if (this.navigationCube) {
                    this.navigationCube.setVisible(true);
                }
            });
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
        const materials = [
            this.materialBase,
            this.materialHover,
            this.materialSelect,
        ];
        let mesh = null;
        if (json?.faces?.bodies) {
            mesh = buildMeshFromOnshapeJson(json, materials);
        } else if (Array.isArray(json?.objects)) {
            mesh = buildMeshFromFreecadJson(json, materials);
        }
        if (!mesh) {
            console.warn("[JsonModelViewer3D] unsupported/empty JSON");
            return;
        }

        // Initialize material indices
        if (Array.isArray(mesh.geometry.groups)) {
            mesh.geometry.groups.forEach((g) => (g.materialIndex = 0));
        }

        this.mesh = mesh;
        this.modelGroup.add(mesh);

        // Update navigation cube orientations and labels from JSON data
        if (this.navigationCube) {
            this.navigationCube.updateOrientationsFromJson(json);
            this.navigationCube.updateFaceLabels({
                front: 'Avant',
                rear: 'Arrière',
                right: 'Droite',
                left: 'Gauche',
                top: 'Dessus',
                bottom: 'Dessous'
            });
        }

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
                captureAndSendScreenshot(
                    this.renderer,
                    this.scene,
                    this.camera,
                    this.container,
                );
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
        const featureData = getFeatureForFaceId(this.features, realId);
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
            this.selectedGroupIndices = getGroupIndicesForFeature(
                featureData,
                this.mesh.userData?.realFaceIdsByGroup,
            );
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
                displayType: getFeatureDisplayType(featureData),
                ...featureData, // Include all feature properties (diameter, depth, position, etc.)
            };
        } else {
            // Fallback to geometric detection
            faceType = detectFaceType(fg, this.mesh.geometry);
            const bbox = {
                min: new THREE.Vector3(minX, minY, minZ),
                max: new THREE.Vector3(maxX, maxY, maxZ),
            };
            metrics = computeFaceMetrics(faceType, vertices, bbox, areaMm2);
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

// Export for use in Blade templates
window.FaceContextParser = FaceContextParser;
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
