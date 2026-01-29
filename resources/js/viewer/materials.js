/**
 * Materials module for JsonModelViewer3D
 * Handles material creation, normal maps, and environment maps
 */
import * as THREE from "three";

/**
 * Create procedural normal maps for different material types
 * @param {string} type - Material type: 'acier', 'inox', 'aluminium', 'cnc'
 * @returns {THREE.CanvasTexture} - The generated normal map texture
 */
export function createNormalMap(type) {
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
                data[i] = Math.max(0, Math.min(255, 128 + circular + noise));
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

                const totalEffect = circularMain + circularSecond + microNoise;

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

/**
 * Create environment map for realistic reflections (studio lighting simulation)
 * @returns {THREE.CanvasTexture} - The generated environment map texture
 */
export function createEnvironmentMap() {
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

    return texture;
}

/**
 * Create the three materials used for face states (base, hover, selected)
 * @param {THREE.Texture} normalMap - The normal map texture to apply
 * @param {THREE.Texture} envMap - The environment map texture for reflections
 * @returns {{base: THREE.MeshPhysicalMaterial, hover: THREE.MeshPhysicalMaterial, select: THREE.MeshPhysicalMaterial}}
 */
export function createMaterials(normalMap, envMap) {
    const materialBase = new THREE.MeshPhysicalMaterial({
        color: "#4a4f54", // gris acier anthracite
        metalness: 1,
        roughness: 0.55, // mat/brut
        clearcoat: 0.05, // quasi pas de vernis
        clearcoatRoughness: 0,
        reflectivity: 0.7,
        side: THREE.DoubleSide,
        normalMap: normalMap,
        normalScale: new THREE.Vector2(0.6, 0.6),
        envMap: envMap,
        envMapIntensity: 1.0,
    });

    const materialHover = materialBase.clone();
    materialHover.color.set("#2d6cff");
    materialHover.normalMap = normalMap;
    materialHover.normalScale = new THREE.Vector2(0.6, 0.6);
    materialHover.envMap = envMap;
    materialHover.envMapIntensity = 1.0;

    const materialSelect = materialBase.clone();
    materialSelect.color.set("#ff3b3b");
    materialSelect.normalMap = normalMap;
    materialSelect.normalScale = new THREE.Vector2(0.6, 0.6);
    materialSelect.envMap = envMap;
    materialSelect.envMapIntensity = 1.0;

    return {
        base: materialBase,
        hover: materialHover,
        select: materialSelect,
    };
}

/**
 * Setup scene lighting for metallic materials
 * @param {THREE.Scene} scene - The Three.js scene to add lights to
 */
export function setupLighting(scene) {
    // Lumière d'ambiance douce (simule le ciel)
    const ambient = new THREE.AmbientLight(0xffffff, 0.4);
    scene.add(ambient);

    // Hemisphere pour environnement studio
    scene.add(new THREE.HemisphereLight(0xffffff, 0xe0e0e0, 0.5));

    // Lumière principale (key light) - crée les reflets principaux
    const key = new THREE.DirectionalLight(0xffffff, 1.8);
    key.position.set(4, 5, 3);
    scene.add(key);

    // Fill light - adoucit les ombres côté gauche
    const fill = new THREE.DirectionalLight(0xffffff, 0.6);
    fill.position.set(-4, 3, 2);
    scene.add(fill);

    // Rim light - crée des reflets sur les arêtes (essentiel pour les métaux)
    const rim = new THREE.DirectionalLight(0xffffff, 0.8);
    rim.position.set(-3, 4, -3);
    scene.add(rim);

    // Top light - simule l'éclairage de studio
    const top = new THREE.DirectionalLight(0xffffff, 0.5);
    top.position.set(0, 5, 0);
    scene.add(top);
}
