import * as THREE from "three";
import "../css/chatbot.scss";

import {
    CSS2DObject,
    CSS2DRenderer,
    FlakesTexture,
    OrbitControls,
    RGBELoader,
} from "three/addons";

let camera,
    scene,
    renderer,
    labelRenderer,
    controls,
    raycaster,
    INTERSECTED,
    intersects,
    width,
    heigth,
    viewer,
    viewerLeft,
    viewerTop;

let labelDiv, labelObject;

const pointer = new THREE.Vector2();

let allMesh = [];
let bodyGroup = new THREE.Group();

Livewire.on("jsonLoaded", function ({ jsonPath }) {

    // On supprime tout ce qu'il y a avant
    scene.remove(bodyGroup);

    allMesh.forEach((mesh) => {
        scene.remove(mesh);
        mesh.geometry.dispose();
        mesh.material.dispose();
        console.log("mesh removed", mesh.name);
    })

    allMesh = [];
    bodyGroup = new THREE.Group();

    // fetch json
    fetch(jsonPath)
        .then((response) => response.json())
        .then((json) => convertJsonToObject(json));
});

const init3dViewer = () => {
    width = viewer.offsetWidth;
    heigth = viewer.offsetHeight;
    viewerLeft = viewer.getBoundingClientRect().left;
    viewerTop = viewer.getBoundingClientRect().top;

    renderer = new THREE.WebGLRenderer({ antialias: true });

    scene = new THREE.Scene();
    scene.background = new THREE.Color(0xffffff);

    // camera

    camera = new THREE.PerspectiveCamera(27, width / heigth, 0.001, 100);
    scene.add( camera );

    // ambient light

    scene.add( new THREE.AmbientLight( 0x666666 ) );

    // point light

    const light = new THREE.PointLight( 0xffffff, 3, 0, 0 );
    camera.add( light );

    // helper

    //scene.add( new THREE.AxesHelper( 20 ) );

    //

    renderer.setPixelRatio(window.devicePixelRatio);
    renderer.setSize(width, heigth);
    renderer.setAnimationLoop(animate);
    viewer.appendChild(renderer.domElement);

    labelRenderer = new CSS2DRenderer();
    labelRenderer.setSize(width, heigth);
    labelRenderer.domElement.style.position = "absolute";
    labelRenderer.domElement.style.top = "10vh";
    viewer.appendChild(labelRenderer.domElement);

    labelDiv = document.createElement("div");
    labelDiv.className = "label";
    labelDiv.style.color = "blue";
    labelObject = new CSS2DObject(labelDiv);
    labelObject.visible = false;
    scene.add(labelObject);

    controls = new OrbitControls(camera, labelRenderer.domElement);
    controls.enableDamping = true;

    window.addEventListener("resize", onWindowResize);

    raycaster = new THREE.Raycaster();

    document.addEventListener("mousemove", onPointerMove);

    detectClicOnObject();
};


const detectClicOnObject = (object) => {
    const delta = 6;
    let startX;
    let startY;

    window.addEventListener('mousedown', function (event) {
        startX = event.pageX;
        startY = event.pageY;
    });

    window.addEventListener('mouseup', function (event) {
        const diffX = Math.abs(event.pageX - startX);
        const diffY = Math.abs(event.pageY - startY);

        if (diffX < delta && diffY < delta) {
            if(intersects.length === 0){
                Livewire.dispatch('chatObjectClick', { 'objectId': null});
            } else {

                intersects.forEach((hit) => {
                    Livewire.dispatch('chatObjectClick', { 'objectId': hit.object.name});
                    console.log(hit.object.name);
                });
            }
        }
    });
};

const convertJsonToObject = (json) => {

    json.faces.bodies.forEach((body) => {
        // Faces
        if (body.faces) {
            body.faces.forEach((faces, index) => {
                // On marque chaque face
                const faceGeometry = new THREE.BufferGeometry();

                const faceVertices = [];
                const faceNormals = [];

                faces.facets.forEach((facet) => {
                    if (facet.indices) {
                        facet.indices.forEach((indice) => {
                            //indices.push(Math.round(indice.x * 10000 ), Math.round(indice.y * 10000 ),Math.round( indice.z * 10000 ));
                        });
                    }

                    if (facet.vertices) {
                        faceNormals.push(
                            facet.normal.x,
                            facet.normal.y,
                            facet.normal.z,
                        );

                        facet.vertices.forEach((vertex) => {
                            faceVertices.push(vertex.x, vertex.y, vertex.z);
                        });
                    }
                });

                const material2 = new THREE.MeshBasicMaterial( { color: 0xb2b2b2 } );

                const meshMaterial = new THREE.MeshLambertMaterial( {
                    color: 0xffffff,
                    opacity: 0.5,
                    //transparent: true
                } );

                faceGeometry.setAttribute(
                    "position",
                    new THREE.Float32BufferAttribute(faceVertices, 3),
                );
                faceGeometry.setAttribute(
                    "normal",
                    new THREE.Float32BufferAttribute(faceNormals, 3),
                );

                const faceMesh = new THREE.Mesh(faceGeometry, material2);
                faceMesh.name = faces.id;

                allMesh.push(faceMesh);

                bodyGroup.add(faceMesh);

                //faceMesh.layers.enableAll();

            });
        }
    });

    scene.add(bodyGroup);


    //

    fitCameraToCenteredObject(camera, bodyGroup, 2, controls);
};

function onWindowResize() {
    width = viewer.offsetWidth;
    heigth = viewer.offsetHeight;
    viewerLeft = viewer.getBoundingClientRect().left;
    viewerTop = viewer.getBoundingClientRect().top;

    camera.aspect = width / heigth;
    camera.updateProjectionMatrix();

    renderer.setSize(width, heigth);
    labelRenderer.setSize(width, heigth);
}

function onPointerMove(event) {
    pointer.x = ((event.clientX - viewerLeft) / width) * 2 - 1;
    pointer.y = -((event.clientY - viewerTop) / heigth) * 2 + 1;
}

//

function animate() {
    raycaster.setFromCamera(pointer, camera);

    intersects = raycaster.intersectObjects(scene.children, true);

    if (intersects.length > 0) {
        if (INTERSECTED != intersects[0].object) {
            if (INTERSECTED)
                INTERSECTED.material.color.setHex(INTERSECTED.currentHex);

            INTERSECTED = intersects[0].object;
            INTERSECTED.currentHex = INTERSECTED.material.color.getHex();
            INTERSECTED.material.color.setHex(0xff0000);

            // Setup label
            labelObject.visible = true;
            labelDiv.textContent = INTERSECTED.name;

            // On affiche le labelObject aux bonnes coordonnées par rapport à l'object INTERSECTED
            const boundingBox = new THREE.Box3().setFromObject(INTERSECTED);

            // Obtenir les limites (min : coin bas gauche ; max : coin haut droit)
            const min = boundingBox.min; // Coin bas gauche
            const max = boundingBox.max; // Coin haut droit

            // Déterminer le coin haut gauche (min.x, max.y)
            const topLeftCorner = new THREE.Vector3(min.x, max.y, min.z); // Garder le "z" du bas-gauche (min.z)

            // Positionner le label au coin haut gauche
            labelObject.position.set(
                topLeftCorner.x,
                topLeftCorner.y,
                topLeftCorner.z,
            );
        }
    } else {
        if (INTERSECTED) {
            INTERSECTED.material.color.setHex(INTERSECTED.currentHex);
            labelObject.visible = false;
            labelDiv.textContent = "";
        }

        INTERSECTED = null;
    }

    controls.update();

    renderer.render(scene, camera);
    labelRenderer.render(scene, camera);
}

const fitCameraToCenteredObject = function (
    camera,
    object,
    offset,
    orbitControls,
) {
    const boundingBox = new THREE.Box3();
    boundingBox.setFromObject(object);

    var middle = new THREE.Vector3();
    var size = new THREE.Vector3();
    boundingBox.getSize(size);

    // figure out how to fit the box in the view:
    // 1. figure out horizontal FOV (on non-1.0 aspects)
    // 2. figure out distance from the object in X and Y planes
    // 3. select the max distance (to fit both sides in)
    //
    // The reason is as follows:
    //
    // Imagine a bounding box (BB) is centered at (0,0,0).
    // Camera has vertical FOV (camera.fov) and horizontal FOV
    // (camera.fov scaled by aspect, see fovh below)
    //
    // Therefore if you want to put the entire object into the field of view,
    // you have to compute the distance as: z/2 (half of Z size of the BB
    // protruding towards us) plus for both X and Y size of BB you have to
    // figure out the distance created by the appropriate FOV.
    //
    // The FOV is always a triangle:
    //
    //  (size/2)
    // +--------+
    // |       /
    // |      /
    // |     /
    // | F° /
    // |   /
    // |  /
    // | /
    // |/
    //
    // F° is half of respective FOV, so to compute the distance (the length
    // of the straight line) one has to: `size/2 / Math.tan(F)`.
    //
    // FTR, from https://threejs.org/docs/#api/en/cameras/PerspectiveCamera
    // the camera.fov is the vertical FOV.

    const fov = camera.fov * (Math.PI / 180);
    const fovh = 2 * Math.atan(Math.tan(fov / 2) * camera.aspect);
    let dx = size.z / 2 + Math.abs(size.x / 2 / Math.tan(fovh / 2));
    let dy = size.z / 2 + Math.abs(size.y / 2 / Math.tan(fov / 2));
    let cameraZ = Math.max(dx, dy);

    // offset the camera, if desired (to avoid filling the whole canvas)
    if (offset !== undefined && offset !== 0) cameraZ *= offset;

    camera.position.set(0, 0, cameraZ);

    // set the far plane of the camera so that it easily encompasses the whole object
    const minZ = boundingBox.min.z;
    const cameraToFarEdge = minZ < 0 ? -minZ + cameraZ : cameraZ - minZ;

    camera.far = cameraToFarEdge * 3;
    camera.updateProjectionMatrix();

    if (orbitControls !== undefined) {
        // set camera to rotate around the center
        orbitControls.target = new THREE.Vector3(0, 0, 0);

        // prevent camera from zooming out far enough to create far plane cutoff
        orbitControls.maxDistance = cameraToFarEdge * 2;
    }
};

viewer = document.getElementById("viewer");

if (viewer) {
    init3dViewer();
}
