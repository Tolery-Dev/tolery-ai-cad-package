/**
 * FaceSelectionManager - Manages face/edge selection chips and context injection
 * Handles the UI for displaying selected faces/edges and injecting context into the chat
 *
 * Context format follows DFM enhanced-face-identification spec:
 * Face: [FACE_CONTEXT: Face Selection: ID[...] Type[...] Position[...] BBox[...] Geometry[...] Context[...]]
 * Edge: [EDGE_CONTEXT: ID[...] Type[Linear Edge] Start(...) End(...) Length[...] Orientation[...]]
 */
export class FaceSelectionManager {
    constructor() {
        this.selectedFaces = new Map();
        this.selectedEdges = new Map();
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

        // Écoute les sélections d'edges depuis le viewer 3D
        window.addEventListener("cad-edge-selection", (event) => {
            const detail = event?.detail ?? null;
            if (detail) {
                this.handleEdgeSelectionDetail(detail);
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
                    if (this.hasSelections()) {
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
                    if (composer && this.hasSelections()) {
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

    hasSelections() {
        return this.selectedFaces.size > 0 || this.selectedEdges.size > 0;
    }

    // ─── Face handling ──────────────────────────────────────────────────

    handleFaceSelectionDetail(detail) {
        if (!detail) return;
        this.findElements();

        const faceId = detail.realFaceId ?? detail.id ?? null;
        if (faceId === null) return;

        const context = this.buildFaceContext(detail);
        const summary = this.buildFaceSummary(detail);
        const label =
            detail.realFaceId || detail.id
                ? "Face " + (detail.realFaceId ?? detail.id)
                : "Face";

        // Efface les anciennes sélections de faces pour n'en garder qu'une seule à la fois
        this.selectedFaces.clear();

        // Ajoute la nouvelle sélection
        this.selectedFaces.set(faceId, {
            context,
            summary,
            label,
            type: "face",
            detail,
        });

        this.renderChips();

        // Focus sur le textarea
        if (this.textarea) {
            this.textarea.focus();
        }

        console.log(
            "[FaceSelectionManager] Face selected: " + faceId,
            this.selectedFaces.size,
            "face selections",
        );
    }

    /**
     * Build enhanced face context string (DFM format)
     * Face Selection: ID[{faceId}] Type[{shapeType} ({confidence}%)] Position[{spatialContext}]
     *   BBox[Min(x,y,z) Max(x,y,z) Size(x,y,z)] Geometry[{props}] Context[{relationships}]
     */
    buildFaceContext(detail) {
        const faceId = detail.realFaceId ?? detail.id;
        const shapeType = this.getShapeType(detail);
        const confidence = this.getConfidence(detail);
        const spatialContext = this.analyzeSpatialContext(detail);
        const bboxStr = this.buildBBoxString(detail);
        const geometryStr = this.buildGeometryString(detail);
        const contextStr = this.buildRelationshipContext(detail);

        let ctx = "Face Selection: ID[" + faceId + "]";
        ctx += " Type[" + shapeType;
        if (confidence > 0) ctx += " (" + confidence + "%)";
        ctx += "]";
        ctx += " Position[" + spatialContext + "]";
        ctx += " " + bboxStr;
        ctx += " Geometry[" + geometryStr + "]";
        ctx += " Context[" + contextStr + "]";

        return ctx;
    }

    getShapeType(detail) {
        // Prefer semantic type from feature data
        if (detail.feature && detail.feature.type) {
            var type = detail.feature.type;
            if (detail.faceType === "thread") return "thread";
            return type;
        }
        // Fallback to geometric detection
        if (detail.faceType) return detail.faceType;
        if (detail.metrics && detail.metrics.displayType)
            return detail.metrics.displayType;
        return "unknown";
    }

    getConfidence(detail) {
        if (detail.metrics && detail.metrics.confidence)
            return detail.metrics.confidence;
        // Semantic data from FreeCad is high confidence
        if (detail.feature && detail.feature.type) return 95;
        // Geometric detection is medium confidence
        if (detail.faceType && detail.faceType !== "unknown") return 75;
        return 0;
    }

    /**
     * Analyze spatial context from centroid position relative to model center
     */
    analyzeSpatialContext(detail) {
        var centroid = detail.centroid;
        if (!centroid) return "center";

        var bboxMin = detail.bboxMin || {};
        var bboxMax = detail.bboxMax || {};

        // Calculate model center from bounding box
        var modelCenterX = ((bboxMin.x || 0) + (bboxMax.x || 0)) / 2;
        var modelCenterY = ((bboxMin.y || 0) + (bboxMax.y || 0)) / 2;
        var modelCenterZ = ((bboxMin.z || 0) + (bboxMax.z || 0)) / 2;

        var parts = [];

        // Vertical (Y axis)
        var sizeY = (bboxMax.y || 0) - (bboxMin.y || 0);
        if (sizeY > 0.1) {
            if (centroid.y > modelCenterY + sizeY * 0.25) parts.push("top");
            else if (centroid.y < modelCenterY - sizeY * 0.25)
                parts.push("bottom");
        }

        // Depth (Z axis)
        var sizeZ = (bboxMax.z || 0) - (bboxMin.z || 0);
        if (sizeZ > 0.1) {
            if (centroid.z > modelCenterZ + sizeZ * 0.25) parts.push("front");
            else if (centroid.z < modelCenterZ - sizeZ * 0.25)
                parts.push("back");
        }

        // Width (X axis)
        var sizeX = (bboxMax.x || 0) - (bboxMin.x || 0);
        if (sizeX > 0.1) {
            if (centroid.x > modelCenterX + sizeX * 0.25) parts.push("right");
            else if (centroid.x < modelCenterX - sizeX * 0.25)
                parts.push("left");
        }

        if (parts.length === 0) {
            return (
                "center, center(" +
                this.toNumber(centroid.x) +
                ", " +
                this.toNumber(centroid.y) +
                ", " +
                this.toNumber(centroid.z) +
                ")"
            );
        }

        return (
            parts.join("-") +
            " face, center(" +
            this.toNumber(centroid.x) +
            ", " +
            this.toNumber(centroid.y) +
            ", " +
            this.toNumber(centroid.z) +
            ")"
        );
    }

    buildBBoxString(detail) {
        var min = detail.bboxMin || {};
        var max = detail.bboxMax || {};
        var size = detail.bbox || {};

        return (
            "BBox[Min(" +
            this.toNumber(min.x) +
            ", " +
            this.toNumber(min.y) +
            ", " +
            this.toNumber(min.z) +
            ") Max(" +
            this.toNumber(max.x) +
            ", " +
            this.toNumber(max.y) +
            ", " +
            this.toNumber(max.z) +
            ") Size(" +
            this.toNumber(size.x) +
            ", " +
            this.toNumber(size.y) +
            ", " +
            this.toNumber(size.z) +
            ")]"
        );
    }

    buildGeometryString(detail) {
        var parts = [];
        var metrics = detail.metrics || {};
        var feature = detail.feature || {};

        // Shape classification
        var shapeType = this.getShapeType(detail);
        if (shapeType && shapeType !== "unknown") parts.push(shapeType);

        // Dimensional data from feature
        if (feature.diameter) parts.push("diameter:" + this.toNumber(feature.diameter));
        if (feature.depth) parts.push("depth:" + this.toNumber(feature.depth));
        if (feature.radius) parts.push("radius:" + this.toNumber(feature.radius));
        if (feature.thread) parts.push("thread:" + feature.thread);
        if (feature.angle) parts.push("angle:" + this.toNumber(feature.angle));

        // Area
        if (detail.area) parts.push("area:" + this.toNumber(detail.area));

        // Vertex count
        if (detail.triangles) parts.push(detail.triangles + " triangles");

        return parts.length > 0 ? parts.join(", ") : "unknown";
    }

    buildRelationshipContext(detail) {
        var parts = [];
        var bbox = detail.bbox || {};
        var bboxMin = detail.bboxMin || {};
        var bboxMax = detail.bboxMax || {};

        // Detect boundary alignment
        var sizeX = bbox.x || 0;
        var sizeY = bbox.y || 0;
        var sizeZ = bbox.z || 0;

        // Check if face is on a boundary plane (very thin in one dimension)
        var threshold = 0.5;
        if (sizeX < threshold && sizeY > threshold && sizeZ > threshold)
            parts.push("X-aligned plane");
        else if (sizeY < threshold && sizeX > threshold && sizeZ > threshold)
            parts.push("Y-aligned plane");
        else if (sizeZ < threshold && sizeX > threshold && sizeY > threshold)
            parts.push("Z-aligned plane");

        // Check boundary positions
        if (Math.abs(bboxMin.x || 0) < threshold || Math.abs(bboxMax.x || 0) < threshold)
            parts.push("X-boundary");
        if (Math.abs(bboxMin.y || 0) < threshold || Math.abs(bboxMax.y || 0) < threshold)
            parts.push("Y-boundary");
        if (Math.abs(bboxMin.z || 0) < threshold || Math.abs(bboxMax.z || 0) < threshold)
            parts.push("Z-boundary");

        return parts.length > 0 ? parts.join(", ") : "isolated";
    }

    buildFaceSummary(detail) {
        var parts = [];
        var shapeType = this.getShapeType(detail);
        if (shapeType && shapeType !== "unknown") parts.push(shapeType);
        if (detail.bbox) {
            var bb = detail.bbox;
            parts.push(
                this.toNumber(bb.x) +
                    "\u00D7" +
                    this.toNumber(bb.y) +
                    "\u00D7" +
                    this.toNumber(bb.z) +
                    " mm",
            );
        }
        if (detail.area) {
            parts.push("~" + this.toNumber(detail.area) + " mm\u00B2");
        }
        return parts.join(" \u2022 ");
    }

    // ─── Edge handling ──────────────────────────────────────────────────

    handleEdgeSelectionDetail(detail) {
        if (!detail) return;
        this.findElements();

        var edgeId = detail.edgeId || detail.id || "unknown";
        var context = this.buildEdgeContext(detail);
        var summary = this.buildEdgeSummary(detail);
        var label = "Edge " + edgeId;

        // Efface les anciennes sélections d'edges pour n'en garder qu'une
        this.selectedEdges.clear();

        this.selectedEdges.set(edgeId, {
            context,
            summary,
            label,
            type: "edge",
            detail,
        });

        this.renderChips();

        if (this.textarea) {
            this.textarea.focus();
        }

        console.log(
            "[FaceSelectionManager] Edge selected: " + edgeId,
            this.selectedEdges.size,
            "edge selections",
        );
    }

    /**
     * Build enhanced edge context string (DFM format)
     * ID[{edgeId}] Type[Linear Edge] Start(x,y,z) End(x,y,z) Length[val] Orientation[X/Y/Z-aligned]
     */
    buildEdgeContext(detail) {
        var edgeId = detail.edgeId || detail.id || "unknown";
        var start = detail.start || {};
        var end = detail.end || {};
        var length = detail.length || 0;
        var orientation = this.detectEdgeOrientation(start, end);

        return (
            "ID[" +
            edgeId +
            "] Type[Linear Edge] Start(" +
            this.toNumber(start.x) +
            ", " +
            this.toNumber(start.y) +
            ", " +
            this.toNumber(start.z) +
            ") End(" +
            this.toNumber(end.x) +
            ", " +
            this.toNumber(end.y) +
            ", " +
            this.toNumber(end.z) +
            ") Length[" +
            this.toNumber(length) +
            "] Orientation[" +
            orientation +
            "]"
        );
    }

    detectEdgeOrientation(start, end) {
        if (!start || !end) return "unknown";
        var dx = Math.abs((end.x || 0) - (start.x || 0));
        var dy = Math.abs((end.y || 0) - (start.y || 0));
        var dz = Math.abs((end.z || 0) - (start.z || 0));
        var threshold = 0.1;

        if (dx > threshold && dy < threshold && dz < threshold) return "X-aligned";
        if (dy > threshold && dx < threshold && dz < threshold) return "Y-aligned";
        if (dz > threshold && dx < threshold && dy < threshold) return "Z-aligned";
        return "diagonal";
    }

    buildEdgeSummary(detail) {
        var parts = [];
        if (detail.length) parts.push(this.toNumber(detail.length) + " mm");
        var orientation = this.detectEdgeOrientation(
            detail.start || {},
            detail.end || {},
        );
        parts.push(orientation);
        return parts.join(" \u2022 ");
    }

    // ─── Rendering ──────────────────────────────────────────────────────

    renderChips() {
        this.findElements();
        if (!this.container) {
            console.warn("[FaceSelectionManager] Chips container not found");
            return;
        }

        this.container.innerHTML = "";

        if (!this.hasSelections()) {
            this.container.classList.add("hidden");
            window.dispatchEvent(
                new CustomEvent("face-selection-changed", {
                    detail: { hasSelection: false },
                }),
            );
            return;
        }

        this.container.classList.remove("hidden");

        // Render face chips
        for (var entry of this.selectedFaces.entries()) {
            this.container.appendChild(
                this.createChip(entry[0], entry[1], "face"),
            );
        }

        // Render edge chips
        for (var entry of this.selectedEdges.entries()) {
            this.container.appendChild(
                this.createChip(entry[0], entry[1], "edge"),
            );
        }

        window.dispatchEvent(
            new CustomEvent("face-selection-changed", {
                detail: { hasSelection: true },
            }),
        );
    }

    createChip(id, selection, type) {
        var chip = document.createElement("div");
        var isFace = type === "face";
        chip.className = isFace
            ? "inline-flex items-center gap-2 px-3 py-1.5 rounded-full border border-violet-200 bg-violet-50 text-violet-700 text-sm font-medium shadow-sm cursor-default transition-all hover:bg-violet-100 dark:border-violet-700 dark:bg-violet-900/40 dark:text-violet-200 dark:hover:bg-violet-900/60"
            : "inline-flex items-center gap-2 px-3 py-1.5 rounded-full border border-green-200 bg-green-50 text-green-700 text-sm font-medium shadow-sm cursor-default transition-all hover:bg-green-100 dark:border-green-700 dark:bg-green-900/40 dark:text-green-200 dark:hover:bg-green-900/60";
        chip.title = selection.summary || selection.context;

        // Icon
        var icon = document.createElement("span");
        icon.innerHTML = isFace
            ? '<svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor"><path d="M11 17a1 1 0 001.447.894l4-2A1 1 0 0017 15V9.236a1 1 0 00-1.447-.894l-4 2a1 1 0 00-.553.894V17zM15.211 6.276a1 1 0 000-1.788l-4.764-2.382a1 1 0 00-.894 0L4.789 4.488a1 1 0 000 1.788l4.764 2.382a1 1 0 00.894 0l4.764-2.382zM4.447 8.342A1 1 0 003 9.236V15a1 1 0 00.553.894l4 2A1 1 0 009 17v-5.764a1 1 0 00-.553-.894l-4-2z"/></svg>'
            : '<svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M3 10a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1z" clip-rule="evenodd"/></svg>';
        chip.appendChild(icon);

        // Label
        var label = document.createElement("span");
        label.textContent = selection.label || (isFace ? "Face " : "Edge ") + id;
        chip.appendChild(label);

        // Remove button
        var self = this;
        var removeBtn = document.createElement("button");
        removeBtn.type = "button";
        removeBtn.className =
            "ml-1 w-4 h-4 flex items-center justify-center rounded-full hover:bg-opacity-20 transition-colors " +
            (isFace
                ? "text-violet-400 hover:text-violet-600 hover:bg-violet-200"
                : "text-green-400 hover:text-green-600 hover:bg-green-200");
        removeBtn.innerHTML =
            '<svg xmlns="http://www.w3.org/2000/svg" class="w-3 h-3" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/></svg>';
        removeBtn.addEventListener("click", function (e) {
            e.preventDefault();
            e.stopPropagation();
            if (isFace) self.selectedFaces.delete(id);
            else self.selectedEdges.delete(id);
            self.renderChips();
        });
        chip.appendChild(removeBtn);

        return chip;
    }

    // ─── Context injection ──────────────────────────────────────────────

    /**
     * Génère la chaîne de contexte à envoyer au moteur
     */
    generateContextString() {
        if (!this.hasSelections()) return "";

        var contexts = [];

        // Face contexts
        for (var sel of this.selectedFaces.values()) {
            contexts.push("[FACE_CONTEXT: " + sel.context + "]");
        }

        // Edge contexts
        for (var sel of this.selectedEdges.values()) {
            contexts.push("[EDGE_CONTEXT: " + sel.context + "]");
        }

        return contexts.join(" ");
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

        var ctx = this.generateContextString();
        if (!ctx) return;

        var currentValue = this.textarea.value || "";
        if (
            currentValue.includes("[FACE_CONTEXT:") ||
            currentValue.includes("[EDGE_CONTEXT:")
        )
            return;

        var newValue = [currentValue.trim(), ctx].filter(Boolean).join(" ");
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
        this.selectedFaces.clear();
        this.selectedEdges.clear();
        this.renderChips();
        console.log("[FaceSelectionManager] Selections cleared");
    }

    /**
     * Retourne les sélections actuelles (pour debug ou API)
     */
    getSelections() {
        var faces = [];
        for (var entry of this.selectedFaces.entries()) {
            faces.push({ id: entry[0], type: "face", ...entry[1] });
        }
        var edges = [];
        for (var entry of this.selectedEdges.entries()) {
            edges.push({ id: entry[0], type: "edge", ...entry[1] });
        }
        return faces.concat(edges);
    }

    toNumber(value) {
        var n = Number(value);
        if (Number.isNaN(n)) return "\u2014";
        return Number.isFinite(n) ? n.toFixed(1) : "\u2014";
    }
}
