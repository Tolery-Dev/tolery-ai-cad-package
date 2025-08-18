// resources/js/app.js
import * as THREE from 'three'
import { OrbitControls } from 'three/addons/controls/OrbitControls.js'
import { mergeGeometries } from 'three/addons/utils/BufferGeometryUtils.js'
import { EffectComposer } from 'three/addons/postprocessing/EffectComposer.js'
import { RenderPass }     from 'three/addons/postprocessing/RenderPass.js'
import { OutlinePass }    from 'three/addons/postprocessing/OutlinePass.js'

// ---------- État global ----------
let scene, camera, renderer, controls, raycaster
let composer, renderPass, hoverOutlinePass, selectOutlinePass

let bodyGroup = new THREE.Group()
let allMeshes = []
let mainEdgesLine = null

let selectedGid = null
let hoveredGid  = null

let intersects = []
const pointer = new THREE.Vector2()

// UI / options
let edgesShow = true
let edgesColor = '#000000'
let edgeThresholdDeg = 45

// couleurs dynamiques
let hoverColorHex  = '#2d6cff'  // par défaut bleu
let selectColorHex = '#ff3b3b'  // par défaut rouge

const BASE_COLOR = 0xb2b2b2

// Heuristique (ajuste selon l’échelle du JSON)
const ANGLE_TOL = 2 * Math.PI / 180
const DIST_TOL  = 0.05
const VERT_TOL  = 1e-3

const viewer = document.getElementById('viewer')

// ---------- Utils heuristique ----------
function norm(v){ const n=v.length(); return n>0 ? v.multiplyScalar(1/n) : v }
function angleBetween(n1, n2){ return Math.acos(THREE.MathUtils.clamp(n1.dot(n2), -1, 1)) }
function planeFromTriangle(a,b,c){
  const n = new THREE.Vector3().subVectors(b,a).cross(new THREE.Vector3().subVectors(c,a))
  norm(n)
  const d = -n.dot(a)
  return { n, d }
}
function pointPlaneDistance(n, d, p){ return Math.abs(n.dot(p)+d) / n.length() }
function vKey(x,y,z){ return `${(x/ VERT_TOL|0)},${(y/ VERT_TOL|0)},${(z/ VERT_TOL|0)}` }

// Grouping heuristique
function buildFaceGroups() {
  const infos = allMeshes.map((mesh, idx) => {
    const pos = mesh.geometry.getAttribute('position')
    const a = new THREE.Vector3(pos.getX(0), pos.getY(0), pos.getZ(0))
    const b = new THREE.Vector3(pos.getX(1), pos.getY(1), pos.getZ(1))
    const c = new THREE.Vector3(pos.getX(2), pos.getY(2), pos.getZ(2))
    const { n, d } = planeFromTriangle(a,b,c)
    const keys = new Set()
    for (let i=0;i<pos.count;i++){
      keys.add(vKey(pos.getX(i), pos.getY(i), pos.getZ(i)))
    }
    return { n, d, keys, idx }
  })

  const adj = Array(infos.length).fill(0).map(()=>[])
  for (let i=0;i<infos.length;i++){
    const ii = infos[i]
    for (let j=i+1;j<infos.length;j++){
      const jj = infos[j]
      if (angleBetween(ii.n, jj.n) > ANGLE_TOL) continue
      const sampleKey = jj.keys.values().next().value
      const [sx,sy,sz] = sampleKey.split(',').map(k => parseInt(k)*VERT_TOL)
      const p = new THREE.Vector3(sx,sy,sz)
      if (pointPlaneDistance(ii.n, ii.d, p) > DIST_TOL) continue
      let shared = 0
      for (const k of jj.keys){ if (ii.keys.has(k)) { shared++; if (shared>=2) break } }
      if (shared>=2){ adj[i].push(j); adj[j].push(i) }
    }
  }

  const groupIdOf = Array(infos.length).fill(-1)
  let gid = 0
  for (let i=0;i<infos.length;i++){
    if (groupIdOf[i] !== -1) continue
    const q=[i]; groupIdOf[i]=gid
    while(q.length){
      const u=q.shift()
      for(const v of adj[u]){
        if (groupIdOf[v]===-1){ groupIdOf[v]=gid; q.push(v) }
      }
    }
    gid++
  }

  for (let i=0;i<infos.length;i++){
    allMeshes[infos[i].idx].userData.groupId = groupIdOf[i]
  }
}

// ---------- Init ----------
function init() {
  scene = new THREE.Scene()
  scene.background = new THREE.Color(0xffffff)

  const width = viewer.clientWidth
  const height = viewer.clientHeight

  camera = new THREE.PerspectiveCamera(45, width / height, 0.1, 2000)
  camera.position.set(0, 0, 5)

  renderer = new THREE.WebGLRenderer({ antialias: true })
  renderer.setPixelRatio(window.devicePixelRatio)
  renderer.setSize(width, height)
  viewer.appendChild(renderer.domElement)

  controls = new OrbitControls(camera, renderer.domElement)
  controls.enableDamping = true

  raycaster = new THREE.Raycaster()

  composer = new EffectComposer(renderer)
  renderPass = new RenderPass(scene, camera)
  composer.addPass(renderPass)

  hoverOutlinePass = new OutlinePass(new THREE.Vector2(width, height), scene, camera)
  hoverOutlinePass.edgeStrength = 2.0
  hoverOutlinePass.edgeThickness = 1.0
  hoverOutlinePass.pulsePeriod = 0
  hoverOutlinePass.visibleEdgeColor.set(hoverColorHex)
  hoverOutlinePass.hiddenEdgeColor.set(hoverColorHex)
  composer.addPass(hoverOutlinePass)

  selectOutlinePass = new OutlinePass(new THREE.Vector2(width, height), scene, camera)
  selectOutlinePass.edgeStrength = 3.5
  selectOutlinePass.edgeThickness = 2.0
  selectOutlinePass.pulsePeriod = 0
  selectOutlinePass.visibleEdgeColor.set(selectColorHex)
  selectOutlinePass.hiddenEdgeColor.set(selectColorHex)
  composer.addPass(selectOutlinePass)

  window.addEventListener('resize', onWindowResize)
  viewer.addEventListener('mousemove', onPointerMove)
  viewer.addEventListener('mousedown', onMouseDown)
  viewer.addEventListener('mouseup', onMouseUp)

  animate()
}

// ---------- Render loop ----------
function animate() {
  requestAnimationFrame(animate)

  raycaster.setFromCamera(pointer, camera)
  intersects = raycaster.intersectObjects(bodyGroup.children, true)
  const hit = intersects.length > 0 ? intersects[0].object : null
  hoveredGid = hit ? (hit.userData.groupId ?? null) : null

  for (const m of allMeshes) m.material.color.set(BASE_COLOR)

  if (selectedGid !== null){
    for (const m of allMeshes) if (m.userData.groupId === selectedGid) m.material.color.set(selectColorHex)
  } else if (hoveredGid !== null){
    for (const m of allMeshes) if (m.userData.groupId === hoveredGid) m.material.color.set(hoverColorHex)
  }

  const repHover  = (selectedGid===null && hoveredGid!==null) ? allMeshes.find(m => m.userData.groupId===hoveredGid) : null
  const repSelect = (selectedGid!==null) ? allMeshes.find(m => m.userData.groupId===selectedGid) : null
  selectOutlinePass.selectedObjects = repSelect ? [repSelect] : []
  hoverOutlinePass .selectedObjects = (!repSelect && repHover) ? [repHover] : []

  controls.update()
  composer.render()
}

// ---------- Resize ----------
function onWindowResize() {
  const width = viewer.clientWidth
  const height = viewer.clientHeight
  camera.aspect = width / height
  camera.updateProjectionMatrix()
  renderer.setSize(width, height)
  composer.setSize(width, height)
  hoverOutlinePass.setSize(width, height)
  selectOutlinePass.setSize(width, height)
}

// ---------- Pointer / Click ----------
function onPointerMove(e) {
  const rect = viewer.getBoundingClientRect()
  pointer.x = ((e.clientX - rect.left) / rect.width) * 2 - 1
  pointer.y = -((e.clientY - rect.top) / rect.height) * 2 + 1
}
let startX, startY
function onMouseDown(e){ startX = e.pageX; startY = e.pageY }
function onMouseUp(e){
  const delta = 6
  const isClick = Math.abs(e.pageX - startX) < delta && Math.abs(e.pageY - startY) < delta
  if (!isClick) return

  raycaster.setFromCamera(pointer, camera)
  const hits = raycaster.intersectObjects(bodyGroup.children, true)

  if (hits.length === 0){
    selectedGid = null
    Livewire.dispatch('chatObjectClick', { objectId: null })
    return
  }

  const gid = hits[0].object.userData.groupId ?? null
  selectedGid = gid
  Livewire.dispatch('chatObjectClick', { objectId: gid })
}

// ---------- Fit caméra + pivot ----------
function fitCameraToObject(object, offset = 2) {
  const box = new THREE.Box3().setFromObject(object)
  const size = new THREE.Vector3()
  const center = new THREE.Vector3()
  box.getSize(size)
  box.getCenter(center)

  const maxDim = Math.max(size.x, size.y, size.z)
  const fov = THREE.MathUtils.degToRad(camera.fov)
  const cameraZ = Math.abs(maxDim / 2 / Math.tan(fov / 2)) * offset

  camera.position.set(center.x, center.y, cameraZ)
  camera.lookAt(center)
  camera.far = cameraZ * 4
  camera.updateProjectionMatrix()

  controls.target.copy(center)
  controls.update()
}

// ---------- Arêtes principales ----------
function buildPrincipalEdges() {
  if (mainEdgesLine) {
    scene.remove(mainEdgesLine)
    mainEdgesLine.geometry.dispose()
    mainEdgesLine.material.dispose()
    mainEdgesLine = null
  }
  if (allMeshes.length === 0) return

  const geoms = []
  for (const m of allMeshes) {
    const g = m.geometry.clone()
    if (!g.attributes.normal || g.attributes.normal.count === 0) g.computeVertexNormals()
    geoms.push(g)
  }
  const merged = mergeGeometries(geoms, false)
  if (!merged.attributes.normal || merged.attributes.normal.count === 0) merged.computeVertexNormals()

  const edgesGeo = new THREE.EdgesGeometry(merged, edgeThresholdDeg)
  const edgesMat = new THREE.LineBasicMaterial({ color: edgesColor })
  mainEdgesLine = new THREE.LineSegments(edgesGeo, edgesMat)
  mainEdgesLine.visible = edgesShow
  scene.add(mainEdgesLine)
}

// ---------- Chargement JSON ----------
async function loadJsonEdges(jsonPath) {
  const res = await fetch(jsonPath)
  const json = await res.json()

  scene.remove(bodyGroup)
  for (const m of allMeshes) { m.geometry.dispose(); m.material.dispose() }
  if (mainEdgesLine) {
    scene.remove(mainEdgesLine)
    mainEdgesLine.geometry.dispose()
    mainEdgesLine.material.dispose()
    mainEdgesLine = null
  }
  selectedGid = null
  hoveredGid  = null
  allMeshes = []
  bodyGroup = new THREE.Group()

  json.faces?.bodies?.forEach(body => {
    body.faces?.forEach(face => {
      face.facets?.forEach(facet => {
        const tri = facet.vertices ?? []
        if (tri.length < 3) return

        const verts = []
        tri.forEach(v => verts.push(v.x, v.y, v.z))

        const geom = new THREE.BufferGeometry()
        geom.setAttribute('position', new THREE.Float32BufferAttribute(verts, 3))

        if (facet.normal){
          const n = facet.normal
          const triCount = Math.floor(tri.length / 3)
          const norms = []
          for (let i=0;i<triCount;i++) norms.push(n.x, n.y, n.z)
          if (norms.length === verts.length) {
            geom.setAttribute('normal', new THREE.Float32BufferAttribute(norms, 3))
          } else {
            geom.computeVertexNormals()
          }
        } else {
          geom.computeVertexNormals()
        }

        const mat = new THREE.MeshBasicMaterial({ color: BASE_COLOR })
        const mesh = new THREE.Mesh(geom, mat)
        bodyGroup.add(mesh)
        allMeshes.push(mesh)
      })
    })
  })

  scene.add(bodyGroup)
  fitCameraToObject(bodyGroup, 2)

  buildFaceGroups()
  buildPrincipalEdges()
}

// recentrer
window.addEventListener('viewer-fit', () => {
  if (bodyGroup) fitCameraToObject(bodyGroup, 2)
})

// snapshot
window.addEventListener('viewer-snapshot', () => {
  // simple snapshot base64 (tu peux envoyer à Livewire si besoin)
  const dataURL = renderer.domElement.toDataURL('image/png')
  // exemple: ouvrir dans un nouvel onglet
  const w = window.open()
  w.document.write('<iframe src="' + dataURL + '" frameborder="0" style="border:0; top:0; left:0; bottom:0; right:0; width:100%; height:100%;" allowfullscreen></iframe>')
})

// ---------- Livewire ----------
Livewire.on('jsonLoaded', () => {
  // OBJ ignoré volontairement
})

Livewire.on('jsonEdgesLoaded', ({ jsonPath }) => {
  if (jsonPath) loadJsonEdges(jsonPath)
})

Livewire.on('toggleShowEdges', ({ show, threshold = null }) => {
  edgesShow = !!show
  if (threshold !== null && typeof threshold === 'number') {
    edgeThresholdDeg = threshold
    buildPrincipalEdges()
  }
  if (mainEdgesLine) mainEdgesLine.visible = edgesShow
})

Livewire.on('updatedEdgeColor', ({ color }) => {
  if (typeof color === 'string' && color.length) {
    edgesColor = color
    if (mainEdgesLine) mainEdgesLine.material.color.set(color)
  }
})

// === Nouveaux events ===
Livewire.on('updatedHoverColor', ({ color }) => {
  if (typeof color === 'string' && color.length) {
    hoverColorHex = color
    hoverOutlinePass.visibleEdgeColor.set(color)
    hoverOutlinePass.hiddenEdgeColor.set(color)
  }
})

Livewire.on('updatedSelectColor', ({ color }) => {
  if (typeof color === 'string' && color.length) {
    selectColorHex = color
    selectOutlinePass.visibleEdgeColor.set(color)
    selectOutlinePass.hiddenEdgeColor.set(color)
  }
})

// ---------- Go ----------
init()
