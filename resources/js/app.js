// resources/js/app.js (simplified JSON viewer)
import * as THREE from 'three'
import {OrbitControls} from 'three/addons/controls/OrbitControls.js'

const MATERIAL_PRESETS = {
    acier: {color: '#8f9499', metalness: 0.6, roughness: 0.45},
    aluminium: {color: '#c9d0d6', metalness: 0.8, roughness: 0.25},
    inox: {color: '#aeb4ba', metalness: 0.7, roughness: 0.3},
}

// --- Minimal viewer class ---
class JsonModelViewer3D {
    constructor(containerId = 'viewer') {
        this.container = document.getElementById(containerId)
        if (!this.container) {
            console.error('[JsonModelViewer3D] container not found:', containerId)
            return
        }

        // three
        this.scene = new THREE.Scene()

        // --- lights : plus lumineux, plus diffus ---
        this.scene.background = new THREE.Color(0xFAFAFB); // blanc doux

        const w = this.container.clientWidth || 800
        const h = this.container.clientHeight || 600
        this.camera = new THREE.PerspectiveCamera(45, w / h, 0.1, 5000)
        this.camera.position.set(0.8, 0.8, 1.6)

        this.renderer = new THREE.WebGLRenderer({antialias: true})
        // --- renderer plus clair ---
        this.renderer.outputColorSpace = THREE.SRGBColorSpace;
        this.renderer.toneMapping = THREE.ACESFilmicToneMapping;
        this.renderer.toneMappingExposure = 1.95;   // un peu plus punchy
        this.renderer.setPixelRatio(window.devicePixelRatio)
        this.renderer.setSize(w, h)
        this.container.innerHTML = ''
        this.container.appendChild(this.renderer.domElement)

        this.scene.add(new THREE.HemisphereLight(0xffffff, 0xcccccc, 0.75)); // ciel/sol doux
        const key = new THREE.DirectionalLight(0xffffff, 1.0);
        key.position.set(2.5, 3, 4);
        this.scene.add(key)
        const fill = new THREE.DirectionalLight(0xffffff, 0.6);
        fill.position.set(-3, 1.5, 2);
        this.scene.add(fill)
        const rim = new THREE.DirectionalLight(0xffffff, 0.4);
        rim.position.set(-2, 3, -3);
        this.scene.add(rim)

        // controls
        this.controls = new OrbitControls(this.camera, this.renderer.domElement)
        this.controls.enableDamping = true

        // picking
        this.raycaster = new THREE.Raycaster()
        this.pointer = new THREE.Vector2()

        // model
        this.modelGroup = new THREE.Group()
        this.scene.add(this.modelGroup)
        this.mesh = null

        // edges (contours)
        this.edgesLine = null
        this.edgesVisible = false
        this.edgeThreshold = 45
        this.edgesColor = '#000000'

        // measure tool (2-clicks)
        this.measureMode = false
        this.measurePoints = []
        this.measurePreviewPoint = null
        this.measureLine = null
        this.measureMaterial = new THREE.LineBasicMaterial({color: 0x7c3aed})
        this.measureLabelEl = null

        // material tri-state
        // matériaux plus clairs (look “maquette”)
        this.materialBase = new THREE.MeshPhysicalMaterial({
            color: '#DDDEE2',           // gris clair
            metalness: 0.15,
            roughness: 0.6,
            clearcoat: 0.9,
            clearcoatRoughness: 0.15,
            side: THREE.DoubleSide,
        });
        this.materialHover = this.materialBase.clone();
        this.materialHover.color.set('#2d6cff');
        this.materialSelect = this.materialBase.clone();
        this.materialSelect.color.set('#ff3b3b');
        this.selectedGroupIndex = null
        this.hoveredGroupIndex = null

        // events
        window.addEventListener('resize', () => this.onResize())
        this.renderer.domElement.addEventListener('mousemove', (e) => this.onPointerMove(e))
        this.renderer.domElement.addEventListener('mouseup', (e) => this.onMouseUp(e))
        this._down = {x: 0, y: 0}
        this.renderer.domElement.addEventListener('mousedown', (e) => {
            this._down.x = e.clientX;
            this._down.y = e.clientY
        })

        this.animate()
    }

    // --- Public API ---
    async loadFromPath(jsonPath) {
        try {
            const res = await fetch(jsonPath)
            if (!res.ok) throw new Error(`HTTP ${res.status}`)
            const json = await res.json()
            this.loadJsonData(json)
        } catch (e) {
            console.error('[JsonModelViewer3D] loadFromPath failed:', e)
        }
    }

    loadJsonData(json) {
        // cleanup
        while (this.modelGroup.children.length) {
            const c = this.modelGroup.children.pop()
            c.geometry?.dispose()
            Array.isArray(c.material) ? c.material.forEach(m => m?.dispose()) : c.material?.dispose()
            this.modelGroup.remove(c)
        }
        this.mesh = null
        this.selectedGroupIndex = null
        this.hoveredGroupIndex = null

        // build
        let mesh = null
        if (json?.faces?.bodies) {
            mesh = this.buildMeshFromOnshapeJson(json)
        } else if (Array.isArray(json?.objects)) {
            mesh = this.buildMeshFromFreecadJson(json)
        }
        if (!mesh) {
            console.warn('[JsonModelViewer3D] unsupported/empty JSON');
            return
        }

        // assign tri-state materials
        mesh.material = [this.materialBase, this.materialHover, this.materialSelect]
        if (Array.isArray(mesh.geometry.groups)) mesh.geometry.groups.forEach(g => g.materialIndex = 0)

        this.mesh = mesh
        this.modelGroup.add(mesh)

        // build edges once and respect current toggle
        this.buildEdges()
        if (this.edgesLine) this.edgesLine.visible = this.edgesVisible

        // dispatch global dimensions (mm)
        const box = new THREE.Box3().setFromObject(this.modelGroup)
        const size = new THREE.Vector3();
        box.getSize(size)
        const detail = {
            sizeX: size.x * 1000,
            sizeY: size.y * 1000,
            sizeZ: size.z * 1000,
            unit: 'mm',
        }
        window.dispatchEvent(new CustomEvent('cad-model-stats', {detail}))

        this.fitCamera()
    }

    // --- Edges / Contours ---
    buildEdges() {
        if (this.edgesLine) {
            this.scene.remove(this.edgesLine)
            this.edgesLine.geometry?.dispose();
            this.edgesLine.material?.dispose()
            this.edgesLine = null
        }
        if (!this.mesh) return
        const geo = new THREE.EdgesGeometry(this.mesh.geometry, this.edgeThreshold)
        const mat = new THREE.LineBasicMaterial({color: this.edgesColor})
        this.edgesLine = new THREE.LineSegments(geo, mat)
        this.scene.add(this.edgesLine)
    }

    toggleEdges(show, threshold = null, color = null) {
        this.edgesVisible = !!show
        if (typeof threshold === 'number') this.edgeThreshold = threshold
        if (typeof color === 'string') this.edgesColor = color
        this.buildEdges()
        if (this.edgesLine) this.edgesLine.visible = this.edgesVisible
    }

    // --- Material presets ---
    applyMaterialPreset(preset) {
        const p = MATERIAL_PRESETS[preset?.toLowerCase?.()] || null
        if (!p) return
        this.materialBase.color.set(p.color)
        this.materialBase.metalness = p.metalness
        this.materialBase.roughness = p.roughness
        this.materialHover.metalness = p.metalness;
        this.materialHover.roughness = p.roughness
        this.materialSelect.metalness = p.metalness;
        this.materialSelect.roughness = p.roughness
        this.updateMaterialStates()
    }

    // --- Measure helpers ---
    ensureMeasureLabel() {
        if (this.measureLabelEl) return
        const el = document.createElement('div')
        Object.assign(el.style, {
            position: 'absolute',
            padding: '4px 8px',
            background: 'rgba(124,58,237,.95)',
            color: '#fff',
            borderRadius: '6px',
            fontSize: '12px',
            pointerEvents: 'none',
            transform: 'translate(-50%, -120%)'
        })
        this.container.appendChild(el)
        this.measureLabelEl = el
    }

    worldToScreen(p) {
        const v = p.clone().project(this.camera)
        return {x: (v.x * 0.5 + 0.5) * this.container.clientWidth, y: (-v.y * 0.5 + 0.5) * this.container.clientHeight}
    }

    setMeasureMode(enabled) {
        this.measureMode = !!enabled;
        if (!enabled) this.resetMeasure();
        this.renderer.domElement.style.cursor = enabled ? 'crosshair' : 'default'
    }

    resetMeasure() {
        this.measurePoints = [];
        this.measurePreviewPoint = null
        if (this.measureLine) {
            this.scene.remove(this.measureLine);
            this.measureLine.geometry?.dispose();
            this.measureLine = null
        }
        if (this.measureLabelEl) {
            this.measureLabelEl.remove();
            this.measureLabelEl = null
        }
    }

    updateMeasureVisual() {
        if (!this.measureMode) return
        const p1 = this.measurePoints[0] || null
        const p2 = (this.measurePoints.length >= 2) ? this.measurePoints[1] : (this.measurePreviewPoint || null)
        if (!p1 || !p2) {
            if (this.measureLine) {
                this.scene.remove(this.measureLine);
                this.measureLine.geometry?.dispose();
                this.measureLine = null
            }
            ;
            if (this.measureLabelEl) {
                this.measureLabelEl.style.display = 'none'
            }
            ;
            return
        }
        const geo = new THREE.BufferGeometry()
        geo.setAttribute('position', new THREE.Float32BufferAttribute([p1.x, p1.y, p1.z, p2.x, p2.y, p2.z], 3))
        if (!this.measureLine) {
            this.measureLine = new THREE.Line(geo, this.measureMaterial);
            this.scene.add(this.measureLine)
        } else {
            this.measureLine.geometry.dispose();
            this.measureLine.geometry = geo
        }
        this.ensureMeasureLabel();
        const mid = p1.clone().add(p2).multiplyScalar(0.5)
        const s = this.worldToScreen(mid)
        const distMM = p1.distanceTo(p2) * 1000
        this.measureLabelEl.textContent = `${distMM.toFixed(2)} mm`
        this.measureLabelEl.style.left = `${s.x}px`;
        this.measureLabelEl.style.top = `${s.y}px`;
        this.measureLabelEl.style.display = 'block'
    }

    resetView(fill = 0.92) {
      this.fitCamera(fill)
      this.requestRender()
    }

    // --- Builders ---
    buildMeshFromOnshapeJson(json) {
        const bodies = json?.faces?.bodies;
        if (!Array.isArray(bodies)) return null

        const pos = [] // non-indexed positions (x,y,z repeated)
        const groups = []
        const faceGroups = []
        const realFaceIds = []

        for (let b = 0; b < bodies.length; b++) {
            const body = bodies[b]
            const faces = body?.faces || []
            for (let f = 0; f < faces.length; f++) {
                const face = faces[f]
                const startBefore = pos.length
                const faceId = (face.id != null) ? String(face.id) : `body${b}_face${f}`

                const facets = face?.facets || []
                for (let k = 0; k < facets.length; k++) {
                    const vtx = facets[k]?.vertices
                    if (!Array.isArray(vtx) || vtx.length < 3) continue
                    if (vtx.length === 3) {
                        // already a triangle
                        pos.push(vtx[0].x, vtx[0].y, vtx[0].z,
                            vtx[1].x, vtx[1].y, vtx[1].z,
                            vtx[2].x, vtx[2].y, vtx[2].z)
                    } else {
                        // fan triangulation
                        for (let i = 2; i < vtx.length; i++) {
                            const a = vtx[0], b = vtx[i - 1], c = vtx[i]
                            pos.push(a.x, a.y, a.z, b.x, b.y, b.z, c.x, c.y, c.z)
                        }
                    }
                }

                const addedFloats = pos.length - startBefore
                const startIndex = startBefore / 3
                const countIndex = addedFloats / 3
                if (countIndex > 0) {
                    groups.push({start: startIndex, count: countIndex})
                    faceGroups.push({start: startIndex, count: countIndex, id: faceId})
                    realFaceIds.push(faceId)
                }
            }
        }

        if (!pos.length || !groups.length) return null

        const geometry = new THREE.BufferGeometry()
        geometry.setAttribute('position', new THREE.Float32BufferAttribute(pos, 3))
        geometry.computeVertexNormals()
        geometry.groups = groups

        const mesh = new THREE.Mesh(geometry, [this.materialBase, this.materialHover, this.materialSelect])
        mesh.userData.faceGroups = faceGroups
        mesh.userData.realFaceIdsByGroup = realFaceIds
        return mesh
    }

    buildMeshFromFreecadJson(json) {
        const objects = json?.objects;
        if (!Array.isArray(objects)) return null

        const positions = []
        const groups = []
        const faceGroups = []
        const realFaceIds = []
        let baseVertex = 0

        for (let oi = 0; oi < objects.length; oi++) {
            const obj = objects[oi]
            const verts = obj?.vertices || []
            const facets = obj?.facets || []
            for (let v = 0; v < verts.length; v++) positions.push(verts[v][0], verts[v][1], verts[v][2])

            for (let fi = 0; fi < facets.length; fi++) {
                const face = facets[fi]
                if (!Array.isArray(face) || face.length < 3) continue
                const start = positions.length / 3
                // build triangles into a temporary array and then map to non-indexed
                const triIndices = []
                triIndices.push(baseVertex + face[0], baseVertex + face[1], baseVertex + face[2])
                for (let k = 3; k < face.length; k++) triIndices.push(baseVertex + face[0], baseVertex + face[k - 1], baseVertex + face[k])

                // expand to non-indexed positions
                const tmp = []
                for (let i = 0; i < triIndices.length; i++) {
                    const vi = triIndices[i]
                    const vx = json.objects[oi].vertices[vi - baseVertex]
                    tmp.push(vx[0], vx[1], vx[2])
                }
                const added = tmp.length / 3
                // append tmp to positions end
                for (let i = 0; i < tmp.length; i++) positions.push(tmp[i])

                groups.push({start, count: added})
                const id = `freecad_obj${oi}_facet${fi}`
                faceGroups.push({start, count: added, id})
                realFaceIds.push(id)
            }
            baseVertex += verts.length
        }

        if (!positions.length || !groups.length) return null

        const geometry = new THREE.BufferGeometry()
        geometry.setAttribute('position', new THREE.Float32BufferAttribute(positions, 3))
        geometry.computeVertexNormals()
        geometry.groups = groups

        const mesh = new THREE.Mesh(geometry, [this.materialBase, this.materialHover, this.materialSelect])
        mesh.userData.faceGroups = faceGroups
        mesh.userData.realFaceIdsByGroup = realFaceIds
        return mesh
    }

    // --- Helpers ---
    fitCamera(fill = 0.92) { // fill = fraction de l’écran à occuper (0..1)
        const box = new THREE.Box3().setFromObject(this.modelGroup)
        const size = new THREE.Vector3()
        const center = new THREE.Vector3()
        box.getSize(size)
        box.getCenter(center)

        // garde-fous
        const eps = 1e-6
        size.x = Math.max(size.x, eps)
        size.y = Math.max(size.y, eps)
        size.z = Math.max(size.z, eps)

        // distance requise pour cadrer en vertical et horizontal
        const vFov = THREE.MathUtils.degToRad(this.camera.fov)
        const hFov = 2 * Math.atan(Math.tan(vFov / 2) * this.camera.aspect)
        const distV = (size.y * 0.5) / Math.tan(vFov / 2)
        const distH = (size.x * 0.5) / Math.tan(hFov / 2)
        const dist = Math.max(distV, distH)

        // marge contrôlée par 'fill' (0.92 ≈ occuper 92% du viewport)
        const targetDist = dist / Math.max(0.05, Math.min(0.98, fill))

        // point de vue isométrique propre
        const dir = new THREE.Vector3(1, 1, 1).normalize()
        this.camera.position.copy(center).add(dir.multiplyScalar(targetDist))
        this.camera.lookAt(center)

        // plage de clipping stable
        this.camera.near = Math.max(0.01, targetDist * 0.02)
        this.camera.far = targetDist * 50
        this.camera.updateProjectionMatrix()

        this.controls.target.copy(center)
        this.controls.update()
    }

    onResize() {
        if (!this.container) return
        const w = this.container.clientWidth || 800
        const h = this.container.clientHeight || 600
        this.camera.aspect = w / h
        this.camera.updateProjectionMatrix()
        this.renderer.setSize(w, h)
    }

    onPointerMove(e) {
        const rect = this.renderer.domElement.getBoundingClientRect()
        this.pointer.x = ((e.clientX - rect.left) / rect.width) * 2 - 1
        this.pointer.y = -((e.clientY - rect.top) / rect.height) * 2 + 1

        if (!this.mesh) return
        this.raycaster.setFromCamera(this.pointer, this.camera)
        const hits = this.raycaster.intersectObject(this.mesh, false)

        if (this.measureMode && this.measurePoints.length === 1) {
            this.measurePreviewPoint = (hits.length > 0) ? hits[0].point.clone() : null
            this.updateMeasureVisual()
        }

        let newHover = null
        if (hits[0]?.faceIndex != null && Array.isArray(this.mesh.geometry.groups)) {
            const triIndex = hits[0].faceIndex
            for (let gi = 0; gi < this.mesh.geometry.groups.length; gi++) {
                const g = this.mesh.geometry.groups[gi]
                const triStart = g.start / 3
                const triEnd = (g.start + g.count) / 3
                if (triIndex >= triStart && triIndex < triEnd) {
                    newHover = gi;
                    break
                }
            }
        }

        if (newHover !== this.hoveredGroupIndex) {
            this.hoveredGroupIndex = newHover
            this.updateMaterialStates()
        }
    }

    onMouseUp(e) {
        const wasDrag = (Math.abs(e.clientX - this._down.x) > 5 || Math.abs(e.clientY - this._down.y) > 5)
        if (wasDrag) return

        if (this.measureMode) {
            this.raycaster.setFromCamera(this.pointer, this.camera)
            const hits = this.raycaster.intersectObject(this.mesh, false)
            if (hits.length > 0) {
                const p = hits[0].point.clone()
                if (this.measurePoints.length < 2) this.measurePoints.push(p); else this.measurePoints = [p]
                this.updateMeasureVisual()
            }
            return
        }

        if (!this.mesh) return
        this.raycaster.setFromCamera(this.pointer, this.camera)
        const hits = this.raycaster.intersectObject(this.mesh, false)
        if (hits.length === 0) {
            this.selectedGroupIndex = null
            this.updateMaterialStates()
            // inform Livewire (clear)
            Livewire?.dispatch?.('chatObjectClick', {objectId: null})
            Livewire?.dispatch?.('chatObjectClickReal', {objectId: null})
            window.dispatchEvent(new CustomEvent('cad-selection', {detail: null}))
            return
        }

        const hit = hits[0]
        // resolve group from triangle index (non-indexed)
        let groupIdx = null
        if (hit.faceIndex != null && Array.isArray(this.mesh.geometry.groups)) {
            const triIndex = hit.faceIndex
            for (let gi = 0; gi < this.mesh.geometry.groups.length; gi++) {
                const g = this.mesh.geometry.groups[gi]
                const triStart = g.start / 3
                const triEnd = (g.start + g.count) / 3
                if (triIndex >= triStart && triIndex < triEnd) {
                    groupIdx = gi;
                    break
                }
            }
        }

        this.selectedGroupIndex = groupIdx
        this.updateMaterialStates()

        const fg = this.mesh.userData?.faceGroups?.[groupIdx]
        const faceId = fg?.id ?? groupIdx
        const realId = this.mesh.userData?.realFaceIdsByGroup?.[groupIdx] ?? faceId

        // Livewire: pré-remplir le chat
        Livewire?.dispatch?.('chatObjectClick', {objectId: faceId})
        Livewire?.dispatch?.('chatObjectClickReal', {objectId: realId})

        // Panneau flottant (centroïde approx + triangles)
        const pos = this.mesh.geometry.getAttribute('position')
        let sx = 0, sy = 0, sz = 0, n = 0
        for (let i = fg.start; i < fg.start + fg.count; i++) {
            sx += pos.getX(i);
            sy += pos.getY(i);
            sz += pos.getZ(i);
            n++
        }
        const centroid = {x: sx / n, y: sy / n, z: sz / n}

        // compute bbox (approx) and area for the face
        let minX = Infinity, minY = Infinity, minZ = Infinity, maxX = -Infinity, maxY = -Infinity, maxZ = -Infinity
        for (let i = fg.start; i < fg.start + fg.count; i++) {
            const x = pos.getX(i), y = pos.getY(i), z = pos.getZ(i)
            if (x < minX) minX = x;
            if (y < minY) minY = y;
            if (z < minZ) minZ = z
            if (x > maxX) maxX = x;
            if (y > maxY) maxY = y;
            if (z > maxZ) maxZ = z
        }
        // area sum over triangles
        const a = new THREE.Vector3(), b = new THREE.Vector3(), c = new THREE.Vector3(), ab = new THREE.Vector3(),
            ac = new THREE.Vector3();
        let areaM2 = 0
        for (let i = fg.start; i < fg.start + fg.count; i += 3) {
            a.set(pos.getX(i), pos.getY(i), pos.getZ(i))
            b.set(pos.getX(i + 1), pos.getY(i + 1), pos.getZ(i + 1))
            c.set(pos.getX(i + 2), pos.getY(i + 2), pos.getZ(i + 2))
            ab.subVectors(b, a);
            ac.subVectors(c, a);
            areaM2 += ab.cross(ac).length() * 0.5
        }
        const detail = {
            id: faceId,
            realFaceId: realId,
            centroid,
            triangles: Math.floor(fg.count / 3),
            unit: 'mm',
            bbox: {x: (maxX - minX) * 1000, y: (maxY - minY) * 1000, z: (maxZ - minZ) * 1000},
            area: +(areaM2 * 1e6).toFixed(2), // mm²
        }
        window.dispatchEvent(new CustomEvent('cad-selection', {detail}))
    }

    updateMaterialStates() {
        if (!this.mesh?.geometry?.groups) return
        for (const g of this.mesh.geometry.groups) g.materialIndex = 0
        if (this.selectedGroupIndex != null) this.mesh.geometry.groups[this.selectedGroupIndex].materialIndex = 2
        else if (this.hoveredGroupIndex != null) this.mesh.geometry.groups[this.hoveredGroupIndex].materialIndex = 1
        this.mesh.material = [this.materialBase, this.materialHover, this.materialSelect]
        this.mesh.material.needsUpdate = true
        this.requestRender()
    }

    requestRender() {
        this._dirty = true
    }

    animate() {
        requestAnimationFrame(() => this.animate())
        this.controls?.update()
        if (this._dirty) {
            this.renderer.render(this.scene, this.camera);
            this._dirty = false
        } else {
            this.renderer.render(this.scene, this.camera)
        }
    }
}

// --- Global wiring ---
let JSON_VIEWER = null

function ensureViewer() {
    if (!JSON_VIEWER) JSON_VIEWER = new JsonModelViewer3D('viewer');
    return JSON_VIEWER
}

// Livewire entry point kept identical: expects { jsonPath }
Livewire.on('jsonEdgesLoaded', ({jsonPath}) => {
    if (!jsonPath) return
    const v = ensureViewer()
    v.loadFromPath(jsonPath)
})

// Fit request from panel
window.addEventListener('viewer-fit', () => {
    ensureViewer().resetView();
})

Livewire.on('toggleShowEdges', ({show, threshold = null, color = null}) => {
    const v = ensureViewer();
    v.toggleEdges(show, threshold, color)
})
Livewire.on('updatedMaterialPreset', ({preset = null, color = null, metalness = null, roughness = null}) => {
    const v = ensureViewer()
    if (preset) v.applyMaterialPreset(preset)
    if (color) {
        v.materialBase.color.set(color);
        v.updateMaterialStates()
    }
    if (Number.isFinite(metalness)) {
        v.materialBase.metalness = +metalness;
        v.updateMaterialStates()
    }
    if (Number.isFinite(roughness)) {
        v.materialBase.roughness = +roughness;
        v.updateMaterialStates()
    }
})
Livewire.on('toggleMeasureMode', ({enabled}) => {
    const v = ensureViewer();
    v.setMeasureMode(!!enabled)
})
Livewire.on('resetMeasure', () => {
    const v = ensureViewer();
    v.resetMeasure()
})

// Boot once so the canvas exists even before data arrives
ensureViewer()
