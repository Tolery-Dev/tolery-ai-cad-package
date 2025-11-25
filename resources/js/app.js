// resources/js/app.js (simplified JSON viewer)
import * as THREE from "three";
import { OrbitControls } from "three/addons/controls/OrbitControls.js";

// --- Minimal viewer class ---
class JsonModelViewer3D {
  constructor(containerId = "viewer") {
    this.container = document.getElementById(containerId);
    if (!this.container) {
      console.error("[JsonModelViewer3D] container not found:", containerId);
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
    this.controls = new OrbitControls(this.camera, this.renderer.domElement);
    this.controls.enableDamping = true;

    // picking
    this.raycaster = new THREE.Raycaster();
    this.pointer = new THREE.Vector2();

    // model
    this.modelGroup = new THREE.Group();
    this.scene.add(this.modelGroup);
    this.mesh = null;

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

    // material tri-state : rendu métallique type studio
    const brushedNormal = this.createNormalMap("inox");
    this.materialBase = new THREE.MeshPhysicalMaterial({
      color: "#b5bec9", // gris métal doux
      metalness: 1,
      roughness: 0.28,
      clearcoat: 0.75,
      clearcoatRoughness: 0.22,
      reflectivity: 0.85,
      side: THREE.DoubleSide,
      normalMap: brushedNormal,
      normalScale: new THREE.Vector2(0.5, 0.5),
    });
    this.materialHover = this.materialBase.clone();
    this.materialHover.color.set("#2d6cff");
    this.materialHover.normalMap = brushedNormal;
    this.materialHover.normalScale = new THREE.Vector2(0.5, 0.5);
    this.materialSelect = this.materialBase.clone();
    this.materialSelect.color.set("#ff3b3b");
    this.materialSelect.normalMap = brushedNormal;
    this.materialSelect.normalScale = new THREE.Vector2(0.5, 0.5);
    this.selectedGroupIndex = null;
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

    this.animate();

    // Force initial resize after DOM is ready to ensure canvas takes full container width
    setTimeout(() => this.onResize(), 100);
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
    this.materialBase.envMapIntensity = 1.6;
    this.materialHover.envMap = texture;
    this.materialHover.envMapIntensity = 1.6;
    this.materialSelect.envMap = texture;
    this.materialSelect.envMapIntensity = 1.6;
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
          const totalX = brushLine + microScratch + lineVariation + finnoise;
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
    this.selectedGroupIndex = null;
    this.hoveredGroupIndex = null;

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
    const detail = {
      sizeX: size.x,
      sizeY: size.y,
      sizeZ: size.z,
      unit: "mm",
    };
    window.Alpine?.dispatchEvent?.("cad-model-stats", detail) ||
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
    const geo = new THREE.EdgesGeometry(this.mesh.geometry, this.edgeThreshold);
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
      background: "rgba(255,255,255,1)",
      color: "#fff",
      borderRadius: "6px",
      fontSize: "12px",
      pointerEvents: "none",
      transform: "translate(-50%, -120%)",
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
    this.renderer.domElement.style.cursor = enabled ? "crosshair" : "default";
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
      new THREE.Float32BufferAttribute([p1.x, p1.y, p1.z, p2.x, p2.y, p2.z], 3),
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
        const faceId = face.id != null ? String(face.id) : `body${b}_face${f}`;

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
              pos.push(a.x, a.y, a.z, b.x, b.y, b.z, c.x, c.y, c.z);
            }
          }
        }

        const addedFloats = pos.length - startBefore;
        const startIndex = startBefore / 3;
        const countIndex = addedFloats / 3;
        if (countIndex > 0) {
          groups.push({ start: startIndex, count: countIndex });
          faceGroups.push({ start: startIndex, count: countIndex, id: faceId });
          realFaceIds.push(faceId);
        }
      }
    }

    if (!pos.length || !groups.length) return null;

    const geometry = new THREE.BufferGeometry();
    geometry.setAttribute("position", new THREE.Float32BufferAttribute(pos, 3));
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
    // fill = fraction de l’écran à occuper (0..1)
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
      this.measurePreviewPoint = hits.length > 0 ? hits[0].point.clone() : null;
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
      this.selectedGroupIndex = null;
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

    this.selectedGroupIndex = groupIdx;
    this.updateMaterialStates();

    const fg = this.mesh.userData?.faceGroups?.[groupIdx];
    const faceId = fg?.id ?? groupIdx;
    const realId = this.mesh.userData?.realFaceIdsByGroup?.[groupIdx] ?? faceId;

    // Note: L'ancien système Livewire (chatObjectClick) est remplacé par
    // le FaceSelectionManager qui gère les chips et l'injection de contexte

    // Panneau flottant (centroïde approx + triangles)
    const pos = this.mesh.geometry.getAttribute("position");
    let sx = 0,
      sy = 0,
      sz = 0,
      n = 0;
    for (let i = fg.start; i < fg.start + fg.count; i++) {
      sx += pos.getX(i);
      sy += pos.getY(i);
      sz += pos.getZ(i);
      n++;
    }
    const centroid = { x: sx / n, y: sy / n, z: sz / n };

    // compute bbox (approx) and area for the face
    let minX = Infinity,
      minY = Infinity,
      minZ = Infinity,
      maxX = -Infinity,
      maxY = -Infinity,
      maxZ = -Infinity;
    for (let i = fg.start; i < fg.start + fg.count; i++) {
      const x = pos.getX(i),
        y = pos.getY(i),
        z = pos.getZ(i);
      if (x < minX) minX = x;
      if (y < minY) minY = y;
      if (z < minZ) minZ = z;
      if (x > maxX) maxX = x;
      if (y > maxY) maxY = y;
      if (z > maxZ) maxZ = z;
    }
    // area sum over triangles
    const a = new THREE.Vector3(),
      b = new THREE.Vector3(),
      c = new THREE.Vector3(),
      ab = new THREE.Vector3(),
      ac = new THREE.Vector3();
    let areaMm2 = 0;
    for (let i = fg.start; i < fg.start + fg.count; i += 3) {
      a.set(pos.getX(i), pos.getY(i), pos.getZ(i));
      b.set(pos.getX(i + 1), pos.getY(i + 1), pos.getZ(i + 1));
      c.set(pos.getX(i + 2), pos.getY(i + 2), pos.getZ(i + 2));
      ab.subVectors(b, a);
      ac.subVectors(c, a);
      areaMm2 += ab.cross(ac).length() * 0.5;
    }
    const detail = {
      id: faceId,
      realFaceId: realId,
      centroid,
      triangles: Math.floor(fg.count / 3),
      unit: "mm",
      bbox: {
        x: maxX - minX,
        y: maxY - minY,
        z: maxZ - minZ,
      },
      area: +areaMm2.toFixed(2), // mm²
    };
    window.Alpine?.dispatchEvent?.("cad-selection", detail) ||
      window.dispatchEvent(new CustomEvent("cad-selection", { detail }));
  }

  updateMaterialStates() {
    if (!this.mesh?.geometry?.groups) return;
    for (const g of this.mesh.geometry.groups) g.materialIndex = 0;
    if (this.selectedGroupIndex != null)
      this.mesh.geometry.groups[this.selectedGroupIndex].materialIndex = 2;
    else if (this.hoveredGroupIndex != null)
      this.mesh.geometry.groups[this.hoveredGroupIndex].materialIndex = 1;
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
              reject(new Error("Failed to create blob from canvas"));
            }
          },
          "image/png",
          0.95,
        );
      } catch (error) {
        console.error("[JsonModelViewer3D] Screenshot capture failed:", error);
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
          Livewire.dispatch("saveClientScreenshot", { base64Data: base64 });
          console.log("[JsonModelViewer3D] Screenshot sent to Livewire");
        } else {
          console.warn(
            "[JsonModelViewer3D] Livewire not available, screenshot not sent",
          );
        }
      };
      reader.onerror = (error) => {
        console.error("[JsonModelViewer3D] Failed to read blob:", error);
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
        document.querySelector('form[wire\\:submit\\.prevent="send"] textarea') ||
        document.getElementById("message");
    }
  }

  setupListeners() {
    // Écoute les sélections de faces depuis le viewer 3D
    window.addEventListener("cad-selection", (event) => {
      const detail = event?.detail ?? null;
      if (detail && detail.id !== undefined) {
        this.handleFaceSelectionDetail(detail);
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
      { capture: true }
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
      { capture: true }
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

    // Ajoute ou remplace la sélection
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
      "selections"
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
        `${this.toNumber(x)}×${this.toNumber(y)}×${this.toNumber(z)} mm`
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
        "ml-1 w-4 h-4 flex items-center justify-center rounded-full text-violet-400 hover:text-violet-600 hover:bg-violet-200 dark:text-violet-300 dark:hover:text-violet-100 dark:hover:bg-violet-700 transition-colors";
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

Livewire.on("toggleShowEdges", ({ show, threshold = null, color = null }) => {
  const v = ensureViewer();
  v.toggleEdges(show, threshold, color);
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
