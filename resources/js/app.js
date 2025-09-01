// resources/js/app.js
import JsonTessellatedLoader from './JsonTessellatedLoader.js'
import * as THREE from 'three'
import {OrbitControls} from 'three/addons/controls/OrbitControls.js'
import {mergeGeometries, mergeVertices} from 'three/addons/utils/BufferGeometryUtils.js'
import {EffectComposer} from 'three/addons/postprocessing/EffectComposer.js'
import {RenderPass} from 'three/addons/postprocessing/RenderPass.js'
import {OutlinePass} from 'three/addons/postprocessing/OutlinePass.js'
import {RoomEnvironment} from 'three/addons/environments/RoomEnvironment.js'

// ---------- Globals ----------
let scene, camera, renderer, controls, raycaster
let composer, renderPass, hoverOutlinePass, selectOutlinePass

let bodyGroup = new THREE.Group()
let allMeshes = []
let mainEdgesLine = null

let selectedGroupIndex = null
let hoveredGroupIndex = null

const pointer = new THREE.Vector2()
const viewer = document.getElementById('viewer')

// UX/Options
let edgesShow = true
let edgesColor = '#000000'
let edgeThresholdDeg = 45

let hoverColorHex = '#2d6cff'
let selectColorHex = '#ff3b3b'
let baseColorHex = '#9ea3a8' // matière par défaut

let dirtyRender = true
let lastPickAt = 0
const PICK_INTERVAL = 80 // ms
const POINTER_EPS = 0.002

// Unités panneau (mm|cm)
let currentUnit = 'mm'

// Matériaux partagés (0=base,1=hover,2=select)
let materialBase = null
let materialHover = null
let materialSelect = null

// ---- Mesure (2 points) ----
let measureMode = false
let measurePoints = []
let measurePreviewPoint = null
let measureLine = null
let measureMaterial = null
let measureLabelEl = null
const measureColorHex = '#7c3aed'

// ---------- Helpers ----------
function toPanelUnit(meters) {
    return currentUnit === 'mm' ? meters * 1000 : meters * 100
}

function worldToScreen(p) {
    const v = p.clone().project(camera)
    return {
        x: (v.x * 0.5 + 0.5) * viewer.clientWidth,
        y: (-v.y * 0.5 + 0.5) * viewer.clientHeight,
    }
}

function ensureMeasureMaterial() {
    if (!measureMaterial) {
        measureMaterial = new THREE.LineBasicMaterial({color: measureColorHex})
    }
}

function ensureMeasureLabel() {
    if (!measureLabelEl) {
        measureLabelEl = document.createElement('div')
        Object.assign(measureLabelEl.style, {
            position: 'absolute',
            padding: '2px 6px',
            borderRadius: '6px',
            background: 'rgba(124,58,237,.95)',
            color: '#fff',
            fontSize: '12px',
            pointerEvents: 'none',
            transform: 'translate(-50%, -100%)',
            whiteSpace: 'nowrap',
        })
        viewer.appendChild(measureLabelEl)
    }
}

function setMeasureMode(enabled) {
    enabled = !!enabled
    if (enabled === measureMode) return
    measureMode = enabled
    if (!enabled) resetMeasure()
    viewer.style.cursor = enabled ? 'crosshair' : 'default'
    dirtyRender = true
}

function resetMeasure() {
    measurePoints = []
    measurePreviewPoint = null
    if (measureLine) {
        scene.remove(measureLine)
        measureLine.geometry?.dispose()
        measureLine = null
    }
    if (measureLabelEl?.parentElement) measureLabelEl.parentElement.removeChild(measureLabelEl)
    measureLabelEl = null
    dirtyRender = true
}

function updateMeasureLabel(p1, p2) {
    if (!p1 || !p2) {
        if (measureLabelEl) measureLabelEl.style.display = 'none'
        return
    }
    ensureMeasureLabel()
    const mid = p1.clone().add(p2).multiplyScalar(0.5)
    const screen = worldToScreen(mid)
    const dist = toPanelUnit(p1.distanceTo(p2))
    measureLabelEl.textContent = `${dist.toFixed(2)} ${currentUnit}`
    measureLabelEl.style.left = `${screen.x}px`
    measureLabelEl.style.top = `${screen.y}px`
    measureLabelEl.style.display = 'block'
}

function updateMeasureVisual() {
    if (!measureMode) return
    const p1 = measurePoints[0] || null
    const p2 = (measurePoints.length >= 2) ? measurePoints[1] : (measurePreviewPoint || null)
    if (!p1 || !p2) {
        updateMeasureLabel(null, null)
        if (measureLine) {
            scene.remove(measureLine)
            measureLine.geometry?.dispose()
            measureLine = null
        }
        return
    }
    ensureMeasureMaterial()
    const geo = new THREE.BufferGeometry()
    geo.setAttribute('position', new THREE.Float32BufferAttribute([p1.x, p1.y, p1.z, p2.x, p2.y, p2.z], 3))
    if (!measureLine) {
        measureLine = new THREE.Line(geo, measureMaterial)
        scene.add(measureLine)
    } else {
        measureLine.geometry.dispose()
        measureLine.geometry = geo
    }
    updateMeasureLabel(p1, p2)
    dirtyRender = true
}

// ---------- Scene ----------
function init() {
    scene = new THREE.Scene()
    scene.background = new THREE.Color(0xffffff)

    const w = viewer.clientWidth
    const h = viewer.clientHeight

    camera = new THREE.PerspectiveCamera(45, w / h, 0.1, 4000)
    camera.position.set(0, 0, 5)

    renderer = new THREE.WebGLRenderer({antialias: true, powerPreference: 'high-performance'})
    renderer.setPixelRatio(window.devicePixelRatio)
    renderer.setSize(w, h)
    renderer.outputColorSpace = THREE.SRGBColorSpace
    renderer.toneMapping = THREE.ACESFilmicToneMapping
    renderer.toneMappingExposure = 1.8
    renderer.setClearColor(0xf5f5f7, 1)
    viewer.appendChild(renderer.domElement)

    // Env lighting (propre/soft) + ambient
    const pmrem = new THREE.PMREMGenerator(renderer)
    scene.environment = pmrem.fromScene(new RoomEnvironment(), 0.04).texture

    const amb = new THREE.AmbientLight(0xffffff, 0.8)
    const key = new THREE.DirectionalLight(0xffffff, 1.2);
    key.position.set(2.5, 3.0, 4.0)
    const fill = new THREE.DirectionalLight(0xffffff, 0.6);
    fill.position.set(-3.0, 1.5, 2.0)
    const rim = new THREE.DirectionalLight(0xffffff, 0.5);
    rim.position.set(-2.0, 3.0, -3.0)
    scene.add(amb, key, fill, rim)

    controls = new OrbitControls(camera, renderer.domElement)
    controls.enableDamping = true
    controls.addEventListener('change', () => {
        dirtyRender = true;
        if (measureMode) updateMeasureVisual()
    })

    raycaster = new THREE.Raycaster()

    composer = new EffectComposer(renderer)
    renderPass = new RenderPass(scene, camera)
    composer.addPass(renderPass)

    hoverOutlinePass = new OutlinePass(new THREE.Vector2(w, h), scene, camera)
    hoverOutlinePass.edgeStrength = 2.0
    hoverOutlinePass.edgeThickness = 1.0
    hoverOutlinePass.pulsePeriod = 0
    hoverOutlinePass.visibleEdgeColor.set(hoverColorHex)
    hoverOutlinePass.hiddenEdgeColor.set(hoverColorHex)
    composer.addPass(hoverOutlinePass)

    selectOutlinePass = new OutlinePass(new THREE.Vector2(w, h), scene, camera)
    selectOutlinePass.edgeStrength = 3.5
    selectOutlinePass.edgeThickness = 2.0
    selectOutlinePass.pulsePeriod = 0
    selectOutlinePass.visibleEdgeColor.set(selectColorHex)
    selectOutlinePass.hiddenEdgeColor.set(selectColorHex)
    composer.addPass(selectOutlinePass)

    window.addEventListener('resize', onResize)
    viewer.addEventListener('mousemove', onPointerMove)
    viewer.addEventListener('mousedown', onMouseDown)
    viewer.addEventListener('mouseup', onMouseUp)

    animate()
}

// ---------- Fit / Edges ----------
function fitCameraToObject(object, offset = 2) {
    const box = new THREE.Box3().setFromObject(object)
    const size = new THREE.Vector3()
    const center = new THREE.Vector3()
    box.getSize(size)
    box.getCenter(center)

    const maxDim = Math.max(size.x, size.y, size.z)
    const fov = THREE.MathUtils.degToRad(camera.fov)
    const camZ = Math.abs(maxDim / 2 / Math.tan(fov / 2)) * offset

    camera.position.set(center.x, center.y, camZ)
    camera.lookAt(center)
    camera.far = camZ * 4
    camera.updateProjectionMatrix()
    controls.target.copy(center)
    controls.update()
}

function buildPrincipalEdges() {
    if (mainEdgesLine) {
        scene.remove(mainEdgesLine)
        mainEdgesLine.geometry?.dispose()
        mainEdgesLine.material?.dispose()
        mainEdgesLine = null
    }
    if (allMeshes.length === 0) return

    let merged
    if (allMeshes.length === 1) {
        merged = allMeshes[0].geometry.clone()
    } else {
        merged = mergeGeometries(allMeshes.map(m => m.geometry.clone()), false)
    }
    const welded = mergeVertices(merged, 1e-3)
    if (!welded.attributes.normal?.count) welded.computeVertexNormals()

    const edgesGeo = new THREE.EdgesGeometry(welded, edgeThresholdDeg)
    const edgesMat = new THREE.LineBasicMaterial({color: edgesColor})
    mainEdgesLine = new THREE.LineSegments(edgesGeo, edgesMat)
    mainEdgesLine.visible = edgesShow
    scene.add(mainEdgesLine)
    dirtyRender = true
}

// ---------- Model stats / selection ----------
function computeAndDispatchModelStats(meshOverride = null) {
    const mesh = meshOverride || allMeshes[0]
    if (!mesh?.geometry) return
    const g = mesh.geometry
    if (!g.boundingBox) g.computeBoundingBox()
    const size = new THREE.Vector3()
    g.boundingBox.getSize(size)
    const maxmm = Math.max(size.x, size.y, size.z) * 1000
    currentUnit = (maxmm >= 1000) ? 'cm' : 'mm'
    const pos = g.getAttribute('position')
    const vertices = pos ? pos.count : 0
    const triangles = g.getIndex() ? (g.getIndex().count / 3) : Math.floor((pos ? pos.count : 0) / 3)
    const detail = {
        vertices,
        triangles,
        sizeX: toPanelUnit(size.x),
        sizeY: toPanelUnit(size.y),
        sizeZ: toPanelUnit(size.z),
        unit: currentUnit,
    }
    window.cadLastStats = detail
    window.dispatchEvent(new CustomEvent('cad-model-stats', {detail}))
}

// ---------- Map vendor face ids ----------
function attachOnshapeFaceIds(mesh, json) {
    try {
        const bodies = json?.faces?.bodies
        if (!mesh || !Array.isArray(mesh.geometry?.groups) || !Array.isArray(bodies) || bodies.length === 0) return
        const ids = []
        for (const b of bodies) {
            if (Array.isArray(b.faces)) for (const f of b.faces) if (f && typeof f.id !== 'undefined') ids.push(f.id)
        }
        if (!ids.length) return
        const faceGroups = mesh.userData.faceGroups
        if (!Array.isArray(faceGroups)) return
        const n = Math.min(faceGroups.length, ids.length)
        for (let i = 0; i < n; i++) if (faceGroups[i]) faceGroups[i].id = ids[i]
        mesh.userData.realFaceIdsByGroup = ids
    } catch (e) {
        console.warn('attachOnshapeFaceIds failed:', e)
    }
}

// ---------- Load JSON (tessellated) ----------
async function loadJsonEdges(jsonPath) {
    resetMeasure()
    const res = await fetch(jsonPath)
    const json = await res.json()

    // cleanup old
    scene.remove(bodyGroup)
    allMeshes.forEach(m => {
        m.geometry?.dispose();
        Array.isArray(m.material) ? m.material.forEach(mm => mm?.dispose()) : m.material?.dispose()
    })
    if (mainEdgesLine) {
        scene.remove(mainEdgesLine)
        mainEdgesLine.geometry?.dispose();
        mainEdgesLine.material?.dispose()
        mainEdgesLine = null
    }
    allMeshes = []
    bodyGroup = new THREE.Group()
    selectedGroupIndex = null
    hoveredGroupIndex = null

    const loader = new JsonTessellatedLoader({
        units: 'mm',
        recenter: true,
        autoscale: true,
        fixWinding: true,
        mergeTolerance: 1e-4,
    })
    const {mesh} = loader.parse(json)

    mesh.userData.areaByGroup = new Map()

    // Matériaux (PBR) lumineux & nets
    materialBase = new THREE.MeshPhysicalMaterial({
        color: baseColorHex,
        metalness: 0.2,
        roughness: 0.35,
        clearcoat: 1.0,
        clearcoatRoughness: 0.1,
        envMapIntensity: 1.4,
        side: THREE.DoubleSide,
    })
    materialHover = materialBase.clone();
    materialHover.color = new THREE.Color(hoverColorHex)
    materialSelect = materialBase.clone();
    materialSelect.color = new THREE.Color(selectColorHex)

    mesh.material = [materialBase, materialHover, materialSelect]
    if (Array.isArray(mesh.geometry.groups)) {
        for (const g of mesh.geometry.groups) g.materialIndex = 0
    }

    // Face ids onshape → group meta
    attachOnshapeFaceIds(mesh, json)

    bodyGroup.add(mesh)
    allMeshes = [mesh]
    scene.add(bodyGroup)

    fitCameraToObject(bodyGroup, 2)
    computeAndDispatchModelStats(mesh)
    buildPrincipalEdges()
    dirtyRender = true
}

// ---------- Resize / Render / Picking ----------
function onResize() {
    const w = viewer.clientWidth, h = viewer.clientHeight
    camera.aspect = w / h
    camera.updateProjectionMatrix()
    renderer.setSize(w, h)
    composer.setSize(w, h)
    hoverOutlinePass.setSize(w, h)
    selectOutlinePass.setSize(w, h)
    if (measureMode) updateMeasureVisual()
    dirtyRender = true
}

let startX, startY

function onMouseDown(e) {
    startX = e.pageX;
    startY = e.pageY
}

function onPointerMove(e) {
    const rect = viewer.getBoundingClientRect()
    const nx = ((e.clientX - rect.left) / rect.width) * 2 - 1
    const ny = -((e.clientY - rect.top) / rect.height) * 2 + 1
    if (Math.abs(nx - pointer.x) + Math.abs(ny - pointer.y) > POINTER_EPS) {
        pointer.x = nx;
        pointer.y = ny
        dirtyRender = true
        if (measureMode && measurePoints.length === 1) {
            raycaster.setFromCamera(pointer, camera)
            const target = allMeshes[0] || bodyGroup
            const hits = raycaster.intersectObject(target, true)
            measurePreviewPoint = (hits.length > 0) ? hits[0].point.clone() : null
            updateMeasureVisual()
        }
    }
}

function onMouseUp(e) {
    const delta = 6
    const isClick = Math.abs(e.pageX - startX) < delta && Math.abs(e.pageY - startY) < delta
    if (!isClick) return

    raycaster.setFromCamera(pointer, camera)
    const target = allMeshes[0] || bodyGroup
    const hits = raycaster.intersectObject(target, true)

    // Mode mesure: capter points puis sortir
    if (measureMode) {
        if (hits.length > 0) {
            const p = hits[0].point.clone()
            if (measurePoints.length < 2) {
                measurePoints.push(p)
            } else {
                measurePoints = [p]
            }
            updateMeasureVisual()
        }
        dirtyRender = true
        return
    }

    if (hits.length === 0) {
        selectedGroupIndex = null
        Livewire.dispatch('chatObjectClick', {objectId: null})
        Livewire.dispatch('chatObjectClickReal', {objectId: null})
        dispatchSelectionDetails(null)
        dirtyRender = true
        return
    }

    const h = hits[0]
    const mesh = allMeshes[0] || h.object
    let groupIdx = null
    if (mesh?.geometry && Array.isArray(mesh.geometry.groups) && h.faceIndex != null) {
        const groups = mesh.geometry.groups
        const g = groups.find(g => h.faceIndex >= (g.start / 3) && h.faceIndex < ((g.start + g.count) / 3))
        if (g) groupIdx = groups.indexOf(g)
    }

    selectedGroupIndex = groupIdx
    const faceId = (mesh?.userData?.faceGroups?.[groupIdx]?.id ?? groupIdx)
    const realId = (mesh?.userData?.realFaceIdsByGroup?.[groupIdx] ?? faceId)

    Livewire.dispatch('chatObjectClick', {objectId: faceId})
    Livewire.dispatch('chatObjectClickReal', {objectId: realId})
    dispatchSelectionDetails(groupIdx)
    dirtyRender = true
}

function animate() {
    requestAnimationFrame(animate)

    const now = performance.now()
    const doPick = dirtyRender && (now - lastPickAt > PICK_INTERVAL)
    let hit = null

    if (doPick) {
        lastPickAt = now
        raycaster.setFromCamera(pointer, camera)
        const target = allMeshes[0] || bodyGroup
        const inter = raycaster.intersectObject(target, true)
        hit = inter[0] || null

        let newHoveredGroupIndex = null
        if (hit?.object?.geometry && Array.isArray(hit.object.geometry.groups) && hit.faceIndex != null) {
            const groups = hit.object.geometry.groups
            const g = groups.find(g => hit.faceIndex >= (g.start / 3) && hit.faceIndex < ((g.start + g.count) / 3))
            if (g) newHoveredGroupIndex = groups.indexOf(g)
        }
        if (newHoveredGroupIndex !== hoveredGroupIndex) {
            hoveredGroupIndex = newHoveredGroupIndex
            dirtyRender = true
        }
    }

    // Affectation des matériaux par groupe (0 base, 1 hover, 2 select)
    const mesh = allMeshes[0] || null
    if (mesh?.geometry && Array.isArray(mesh.geometry.groups)) {
        const groups = mesh.geometry.groups
        // reset materialIndex sur groupes touchés
        for (const g of groups) g.materialIndex = 0
        if (selectedGroupIndex != null && groups[selectedGroupIndex]) groups[selectedGroupIndex].materialIndex = 2
        else if (hoveredGroupIndex != null && groups[hoveredGroupIndex]) groups[hoveredGroupIndex].materialIndex = 1
        mesh.material = [materialBase, materialHover, materialSelect]
        mesh.material.needsUpdate = true

        // Outline
        selectOutlinePass.selectedObjects = (selectedGroupIndex != null) ? [mesh] : []
        hoverOutlinePass.selectedObjects = (selectedGroupIndex == null && hoveredGroupIndex != null) ? [mesh] : []
    }

    controls.update()
    if (dirtyRender) {
        composer.render()
        dirtyRender = false
    }
}

// Aire d'un groupe de triangles en m² (somme des |(b-a)x(c-a)| / 2)
function computeGroupAreaM2(geometry, group, cache) {
    if (!geometry || !group) return 0
    // cache par index de groupe pour éviter de recalculer
    const gid = group.__id ?? `${group.start}:${group.count}`
    if (cache && typeof cache.get === 'function' && cache.has(gid)) return cache.get(gid)

    const index = geometry.getIndex()
    const position = geometry.getAttribute('position')
    if (!index || !position) return 0

    const start = group.start
    const end = start + group.count

    const vA = new THREE.Vector3()
    const vB = new THREE.Vector3()
    const vC = new THREE.Vector3()
    const ab = new THREE.Vector3()
    const ac = new THREE.Vector3()

    let area = 0
    for (let i = start; i < end; i += 3) {
        const a = index.getX(i)
        const b = index.getX(i + 1)
        const c = index.getX(i + 2)

        vA.fromBufferAttribute(position, a)
        vB.fromBufferAttribute(position, b)
        vC.fromBufferAttribute(position, c)

        ab.subVectors(vB, vA)
        ac.subVectors(vC, vA)

        // aire du triangle = 0.5 * |ab x ac|
        area += ab.cross(ac).length() * 0.5
    }

    if (cache && typeof cache.set === 'function') cache.set(gid, area)
    return area
}

// Convertit une aire m² -> unité courante (mm² / cm²)
function areaToPanelUnit(m2, unit) {
    if (unit === 'cm') return m2 * 1e4  // 1 m² = 10 000 cm²
    return m2 * 1e6                     // 1 m² = 1 000 000 mm²
}

function dispatchSelectionDetails(groupIdx) {
    const mesh = allMeshes[0]
    if (!mesh?.geometry || groupIdx == null) {
        window.dispatchEvent(new CustomEvent('cad-selection', {detail: null}))
        return
    }
    const g = mesh.geometry
    const idx = g.getIndex()
    const pos = g.getAttribute('position')
    const fg = mesh.userData.faceGroups?.[groupIdx]
    if (!idx || !pos || !fg) {
        window.dispatchEvent(new CustomEvent('cad-selection', {detail: null}))
        return
    }
    const start = Math.max(0, Math.min(idx.count, fg.start))
    const count = Math.max(0, Math.min(idx.count - start, fg.count))

    // centroïde approx. (moyenne des sommets uniques)
    const uniq = new Set()
    let sx = 0, sy = 0, sz = 0
    for (let i = start; i < start + count; i++) {
        const vi = idx.getX(i)
        if (!uniq.has(vi)) {
            uniq.add(vi)
            sx += pos.getX(vi)
            sy += pos.getY(vi)
            sz += pos.getZ(vi)
        }
    }
    const vc = uniq.size || 1
    const centroid = {x: toPanelUnit(sx / vc), y: toPanelUnit(sy / vc), z: toPanelUnit(sz / vc)}

    // --- NOUVEAU : aire en m² -> unité panneau (mm²/cm²) + cache ---
    const areaM2 = computeGroupAreaM2(g, fg, mesh.userData.areaByGroup)
    const areaUnit = areaToPanelUnit(areaM2, currentUnit)
    const realId = (mesh.userData?.realFaceIdsByGroup && mesh.userData.realFaceIdsByGroup[groupIdx])
        ? mesh.userData.realFaceIdsByGroup[groupIdx]
        : (fg.id ?? null)

    const detail = {
        id: fg.id ?? groupIdx,
        realFaceId: realId,
        centroid,
        vertexCount: vc,
        triangles: Math.floor(count / 3),
        area: +areaUnit.toFixed(2),           // ← aire convertie et arrondie
        unit: currentUnit,                    // ← 'mm' ou 'cm' (donc mm² / cm²)
    }

    window.dispatchEvent(new CustomEvent('cad-selection', {detail}))
}

// ---------- Livewire bindings ----------
Livewire.on('jsonEdgesLoaded', ({jsonPath}) => {
    if (jsonPath) loadJsonEdges(jsonPath)
})

Livewire.on('toggleShowEdges', ({show, threshold = null}) => {
    edgesShow = !!show
    if (typeof threshold === 'number') {
        edgeThresholdDeg = threshold
        buildPrincipalEdges()
    }
    if (mainEdgesLine) mainEdgesLine.visible = edgesShow
    dirtyRender = true
})

Livewire.on('updatedEdgeColor', ({color}) => {
    if (typeof color === 'string' && color.length) {
        edgesColor = color
        if (mainEdgesLine) mainEdgesLine.material.color.set(color)
        dirtyRender = true
    }
})

Livewire.on('updatedHoverColor', ({color}) => {
    if (typeof color === 'string' && color.length) {
        hoverColorHex = color
        hoverOutlinePass.visibleEdgeColor.set(color)
        hoverOutlinePass.hiddenEdgeColor.set(color)
        // aussi appliquer au matériau hover
        if (materialHover) {
            materialHover.color.set(color);
            materialHover.needsUpdate = true
        }
        dirtyRender = true
    }
})

Livewire.on('updatedSelectColor', ({color}) => {
    if (typeof color === 'string' && color.length) {
        selectColorHex = color
        selectOutlinePass.visibleEdgeColor.set(color)
        selectOutlinePass.hiddenEdgeColor.set(color)
        if (materialSelect) {
            materialSelect.color.set(color);
            materialSelect.needsUpdate = true
        }
        dirtyRender = true
    }
})

Livewire.on('updatedMaterialColor', ({color}) => {
    baseColorHex = color
    if (materialBase) {
        materialBase.color.set(color)
        materialBase.needsUpdate = true
    }
    dirtyRender = true
})

Livewire.on('updatedMaterialPreset', ({color, metalness, roughness}) => {
    if (materialBase) {
        if (typeof color === 'string' && color.length) materialBase.color.set(color)
        if (Number.isFinite(metalness)) materialBase.metalness = Number(metalness)
        if (Number.isFinite(roughness)) materialBase.roughness = Number(roughness)
        // sync aux autres
        if (materialHover) {
            materialHover.metalness = materialBase.metalness;
            materialHover.roughness = materialBase.roughness
        }
        if (materialSelect) {
            materialSelect.metalness = materialBase.metalness;
            materialSelect.roughness = materialBase.roughness
        }
    }
    dirtyRender = true
})

Livewire.on('toggleWireframe', ({enabled}) => {
    const m = allMeshes[0]
    if (m) {
        const mats = Array.isArray(m.material) ? m.material : [m.material]
        mats.forEach(mat => {
            if (mat) mat.wireframe = !!enabled
        })
        dirtyRender = true
    }
})

Livewire.on('toggleMeasureMode', ({enabled}) => setMeasureMode(!!enabled))
Livewire.on('resetMeasure', resetMeasure)

window.addEventListener('viewer-fit', () => {
    if (bodyGroup) fitCameraToObject(bodyGroup, 2);
    dirtyRender = true
})
window.addEventListener('viewer-repair-normals', () => {
    repairNormals(1e-4);
    dirtyRender = true
})

// ---------- Normals repair ----------
function repairNormals(threshold = 1e-3) {
    for (const m of allMeshes) {
        const g = m.geometry.clone()
        let welded = mergeVertices(g, threshold)
        welded.computeVertexNormals()
        m.geometry.dispose()
        m.geometry = welded
    }
    buildPrincipalEdges()
    dirtyRender = true
}

// ---------- Boot ----------
init()
