import * as THREE from 'three'
import { OrbitControls } from 'three/addons/controls/OrbitControls.js'

// variables globales du viewer
let scene, camera, renderer, controls, raycaster
let bodyGroup = new THREE.Group()
let allMeshes = []              // stocke les meshes des faces
let edgesLines = []             // stocke les segments d’arêtes
let intersects = []             // intersections raycaster lors du survol
let edgesShow = false           // visibilité des arêtes
let edgesColor = '#ffffff'      // couleur des arêtes
const pointer = new THREE.Vector2()

// récupère l’élément <div id="viewer"> pour y attacher le renderer
const viewer = document.getElementById('viewer')

// --- Initialisation du viewer 3D ---
function init() {
    // scène, caméra, renderer
    scene = new THREE.Scene()
    scene.background = new THREE.Color(0xffffff)

    const width = viewer.clientWidth
    const height = viewer.clientHeight
    camera = new THREE.PerspectiveCamera(45, width / height, 0.1, 1000)
    camera.position.z = 5

    renderer = new THREE.WebGLRenderer({ antialias: true })
    renderer.setPixelRatio(window.devicePixelRatio)
    renderer.setSize(width, height)
    viewer.appendChild(renderer.domElement)

    controls = new OrbitControls(camera, renderer.domElement)
    controls.enableDamping = true

    raycaster = new THREE.Raycaster()

    // événements pour mise à jour du pointeur et gestion du clic
    window.addEventListener('resize', onWindowResize)
    viewer.addEventListener('mousemove', onPointerMove)
    viewer.addEventListener('mousedown', onMouseDown)
    viewer.addEventListener('mouseup', onMouseUp)

    animate()
}

// --- Boucle de rendu ---
function animate() {
    requestAnimationFrame(animate)

    // met à jour le raycaster
    raycaster.setFromCamera(pointer, camera)
    intersects = raycaster.intersectObjects(bodyGroup.children, true)

    // met à jour la couleur de toutes les faces à gris, puis passe la face survolée en rouge
    bodyGroup.children.forEach((mesh) => {
        mesh.material.color.set(0xb2b2b2)
    })
    if (intersects.length > 0) {
        intersects[0].object.material.color.set(0xff0000)
    }

    controls.update()
    renderer.render(scene, camera)
}

// --- Gestion du redimensionnement ---
function onWindowResize() {
    const width = viewer.clientWidth
    const height = viewer.clientHeight
    camera.aspect = width / height
    camera.updateProjectionMatrix()
    renderer.setSize(width, height)
}

// --- Mise à jour du pointeur (NDC) ---
function onPointerMove(event) {
    const rect = viewer.getBoundingClientRect()
    pointer.x = ((event.clientX - rect.left) / rect.width) * 2 - 1
    pointer.y = -((event.clientY - rect.top) / rect.height) * 2 + 1
}

// variables temporaires pour détecter le clic (clic = souris immobile)
let startX, startY
function onMouseDown(event) {
    startX = event.pageX
    startY = event.pageY
}
function onMouseUp(event) {
    const delta = 6
    const diffX = Math.abs(event.pageX - startX)
    const diffY = Math.abs(event.pageY - startY)
    if (diffX < delta && diffY < delta) {
        if (intersects.length === 0) {
            // clic en dehors de la pièce
            Livewire.dispatch('chatObjectClick', { objectId: null })
        } else {
            // clic sur des faces, on envoie l’ID de chaque face sélectionnée
            intersects.forEach((hit) => {
                Livewire.dispatch('chatObjectClick', { objectId: hit.object.name })
            })
        }
    }
}

// --- Ajuste la caméra pour contenir l’objet ---
function fitCameraToObject(object, offset = 2) {
    const box = new THREE.Box3().setFromObject(object)
    const size = new THREE.Vector3()
    const center = new THREE.Vector3()
    box.getSize(size)
    box.getCenter(center)

    const maxDim = Math.max(size.x, size.y, size.z)
    const fov = THREE.MathUtils.degToRad(camera.fov)
    let cameraZ = Math.abs(maxDim / 2 / Math.tan(fov / 2)) * offset

    camera.position.set(center.x, center.y, cameraZ)
    camera.lookAt(center)

    camera.far = cameraZ * 4
    camera.updateProjectionMatrix()
}

// --- Chargement d’un fichier JSON contenant les faces et arêtes ---
async function loadJsonEdges(jsonPath) {
    const response = await fetch(jsonPath)
    const json = await response.json()

    // on vide la scène
    scene.remove(bodyGroup)
    allMeshes.forEach(mesh => {
        mesh.geometry.dispose()
        mesh.material.dispose()
    })
    edgesLines.forEach(line => {
        scene.remove(line)
        line.geometry.dispose()
        line.material.dispose()
    })
    bodyGroup = new THREE.Group()
    allMeshes = []
    edgesLines = []

    // on crée les faces et les arêtes à partir du JSON
    json.faces.bodies.forEach(body => {
        if (body.faces) {
            body.faces.forEach(face => {
                const vertices = []
                const normals = []

                face.facets.forEach(facet => {
                    if (facet.vertices) {
                        facet.vertices.forEach(vertex => {
                            vertices.push(vertex.x, vertex.y, vertex.z)
                        })
                        const n = facet.normal
                        normals.push(n.x, n.y, n.z)
                    }
                })

                const geometry = new THREE.BufferGeometry()
                geometry.setAttribute('position', new THREE.Float32BufferAttribute(vertices, 3))
                geometry.setAttribute('normal', new THREE.Float32BufferAttribute(normals, 3))

                // matériau gris par défaut
                const material = new THREE.MeshLambertMaterial({ color: 0xb2b2b2 })

                const mesh = new THREE.Mesh(geometry, material)
                mesh.name = face.id
                bodyGroup.add(mesh)
                allMeshes.push(mesh)

                // création des lignes d’arêtes
                const edges = new THREE.EdgesGeometry(geometry)
                const lineMaterial = new THREE.LineBasicMaterial({ color: edgesColor })
                const line = new THREE.LineSegments(edges, lineMaterial)
                line.visible = edgesShow
                scene.add(line)
                edgesLines.push(line)
            })
        }
    })

    scene.add(bodyGroup)
    fitCameraToObject(bodyGroup, 2)
}

// --- (Optionnel) Chargement d’un fichier OBJ classique ---
function loadObj(objPath) {
    const loader = new THREE.OBJLoader()
    loader.load(
        objPath,
        (object) => {
            scene.remove(bodyGroup)
            bodyGroup = object
            scene.add(object)
            fitCameraToObject(object, 2)
        },
        undefined,
        (error) => {
            console.error('Error loading OBJ:', error)
        }
    )
}

// ----------------------------------------------------------------------
// Écouteurs Livewire : conservent les noms d’événements existants
// ----------------------------------------------------------------------
Livewire.on('jsonLoaded', ({ objPath }) => {
    // compatibilité avec l’ancienne logique OBJ : on peut ignorer ou charger un OBJ
    if (objPath) {
        loadObj(objPath)
    }
})

Livewire.on('jsonEdgesLoaded', ({ jsonPath }) => {
    if (jsonPath) {
        loadJsonEdges(jsonPath)
    }
})

Livewire.on('toggleShowEdges', ({ show }) => {
    edgesShow = show
    edgesLines.forEach(line => {
        line.visible = show
    })
})

Livewire.on('updatedEdgeColor', ({ color }) => {
    edgesColor = color
    edgesLines.forEach(line => {
        line.material.color.set(color)
    })
})

// ----------------------------------------------------------------------
viewer = document.getElementById('viewer')

if (viewer) {
    init()
}
