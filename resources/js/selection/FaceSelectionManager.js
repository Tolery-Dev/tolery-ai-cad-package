/**
 * FaceSelectionManager - Manages face selection chips and context injection
 * Handles the UI for displaying selected faces and injecting context into the chat
 */
export class FaceSelectionManager {
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
            console.log(
                "[DEBUG] Dispatching face-selection-changed with hasSelection:",
                false,
            );
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

        console.log(
            "[DEBUG] Dispatching face-selection-changed with hasSelection:",
            true,
        );
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
