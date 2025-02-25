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
    viewerTop,
    material;

let labelDiv, labelObject;

const pointer = new THREE.Vector2();

Livewire.on("jsonLoaded", function ({ jsonPath }) {
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
    scene.background = new THREE.Color(0xb8c9d4);

    // Eclairage
    const ambient = new THREE.AmbientLight(0xffffff, 1);
    scene.add(ambient);

    const pointLight = new THREE.PointLight(0xffffff, 1, 100);
    pointLight.position.set(50, 50, 50);
    scene.add(pointLight);

    const frontSpot2 = new THREE.SpotLight(0xffffff, 1, 100);
    frontSpot2.position.set(-80, -80, -80);
    scene.add(frontSpot2);

    //

    camera = new THREE.PerspectiveCamera(27, width / heigth, 0.001, 100);

    //

    material = new THREE.MeshBasicMaterial({
        color: 0x94a3ad,
    });
};

const convertJsonToObject = (json) => {
    const bodyGroup = new THREE.Group();

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

                // const material2 = new THREE.MeshBasicMaterial( { color: 0xffffff } );

                faceGeometry.setAttribute(
                    "position",
                    new THREE.Float32BufferAttribute(faceVertices, 3),
                );
                faceGeometry.setAttribute(
                    "normal",
                    new THREE.Float32BufferAttribute(faceNormals, 3),
                );

                const faceMesh = new THREE.Mesh(faceGeometry, material);
                faceMesh.name = faces.id;

                bodyGroup.add(faceMesh);

                faceMesh.layers.enableAll();

                // const faceDiv = document.createElement( 'div' );
                // faceDiv.className = 'label';
                // faceDiv.textContent = faces.id;
                // faceDiv.style.backgroundColor = 'blue';
                //
                // //
                //
                // faceMesh.geometry.computeBoundingBox();
                //
                // const boundingBox = faceMesh.geometry.boundingBox;
                //
                // const position = new THREE.Vector3();
                // position.subVectors( boundingBox.max, boundingBox.min );
                // position.multiplyScalar( 0.5 );
                // position.add( boundingBox.min );
                //
                // position.applyMatrix4( faceMesh.matrixWorld );
                //
                // //
                //
                // const faceLabel = new CSS2DObject( faceDiv );
                // faceLabel.position.set( position.x, position.y, position.z );
                // faceLabel.center.set( 0, 0 );
                // faceMesh.add( faceLabel );
                // faceLabel.layers.set( 0 );
            });
        }
    });

    scene.add(bodyGroup);

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

    //

    fitCameraToCenteredObject(camera, bodyGroup, 2, controls);

    window.addEventListener("resize", onWindowResize);

    raycaster = new THREE.Raycaster();

    document.addEventListener("mousemove", onPointerMove);

    window.addEventListener("click", (e) => {
        intersects.forEach((hit) => {
            console.log(hit.object.name);
        });
    });

    function onPointerMove(event) {
        pointer.x = ((event.clientX - viewerLeft) / width) * 2 - 1;
        pointer.y = -((event.clientY - viewerTop) / heigth) * 2 + 1;
    }
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
