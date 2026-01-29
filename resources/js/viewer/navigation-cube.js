/**
 * NavigationCube - Onshape-style navigation cube
 * Components:
 * - Main cube with 6 labeled faces (Front, Rear, Top, Bottom, Left, Right)
 * - XYZ axis indicators with colored labels
 * - Dynamic orientation labels from JSON model data
 */
import * as THREE from "three";

export class NavigationCube {
    constructor(containerElement, mainCamera, mainControls) {
        this.container = containerElement;
        this.mainCamera = mainCamera;
        this.mainControls = mainControls;

        // State
        this.hoveredObject = null;
        this.isAnimating = false;

        // Default face labels (can be updated from JSON orientation data)
        this.faceLabels = {
            front: 'Front',
            rear: 'Rear',
            right: 'Right',
            left: 'Left',
            top: 'Top',
            bottom: 'Bottom'
        };

        // Colors - Onshape style
        this.colors = {
            cubeBase: '#f3f4f6',
            cubeHover: '#c4b5fd',
            cubeBorder: '#a1a1aa',
            text: '#374151',
            axisX: 0xef4444,
            axisY: 0x22c55e,
            axisZ: 0x3b82f6
        };

        // Setup renderer
        this.renderer = new THREE.WebGLRenderer({
            canvas: containerElement,
            alpha: true,
            antialias: true
        });
        this.renderer.setSize(180, 180);
        this.renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));
        this.renderer.setClearColor(0x000000, 0);

        // Setup scene
        this.scene = new THREE.Scene();

        // Setup camera
        this.camera = new THREE.OrthographicCamera(-2.2, 2.2, 2.2, -2.2, 0.1, 20);
        this.camera.position.set(0, 0, 5);
        this.camera.lookAt(0, 0, 0);

        // Lighting
        this.scene.add(new THREE.AmbientLight(0xffffff, 0.8));
        const dirLight = new THREE.DirectionalLight(0xffffff, 0.3);
        dirLight.position.set(2, 3, 4);
        this.scene.add(dirLight);

        // Create components
        this.createCube();
        this.createAxes();

        // Raycaster
        this.raycaster = new THREE.Raycaster();
        this.mouse = new THREE.Vector2();

        // Events
        this.onClick = this.onClick.bind(this);
        this.onMouseMove = this.onMouseMove.bind(this);
        this.onMouseLeave = this.onMouseLeave.bind(this);

        this.container.addEventListener('click', this.onClick);
        this.container.addEventListener('mousemove', this.onMouseMove);
        this.container.addEventListener('mouseleave', this.onMouseLeave);

        this.render();
    }

    createCube() {
        this.cubeGroup = new THREE.Group();
        this.cubeFaces = [];

        const size = 1.3;
        const halfSize = size / 2;

        // Face definitions
        this.faceDefinitions = [
            { key: 'front', pos: [0, 0, halfSize], rot: [0, 0, 0], normal: [0, 0, 1] },
            { key: 'rear', pos: [0, 0, -halfSize], rot: [0, Math.PI, 0], normal: [0, 0, -1] },
            { key: 'right', pos: [halfSize, 0, 0], rot: [0, Math.PI / 2, 0], normal: [1, 0, 0] },
            { key: 'left', pos: [-halfSize, 0, 0], rot: [0, -Math.PI / 2, 0], normal: [-1, 0, 0] },
            { key: 'top', pos: [0, halfSize, 0], rot: [-Math.PI / 2, 0, 0], normal: [0, 1, 0] },
            { key: 'bottom', pos: [0, -halfSize, 0], rot: [Math.PI / 2, 0, 0], normal: [0, -1, 0] }
        ];

        this.faceDefinitions.forEach(face => {
            const label = this.faceLabels[face.key];
            const texture = this.createFaceTexture(label);
            const geometry = new THREE.PlaneGeometry(size, size);
            const material = new THREE.MeshBasicMaterial({
                map: texture,
                side: THREE.DoubleSide,
                transparent: true
            });

            const mesh = new THREE.Mesh(geometry, material);
            mesh.position.set(...face.pos);
            mesh.rotation.set(...face.rot);
            mesh.userData = {
                type: 'face',
                key: face.key,
                name: label,
                normal: new THREE.Vector3(...face.normal),
                originalTexture: texture,
                isHovered: false
            };

            this.cubeGroup.add(mesh);
            this.cubeFaces.push(mesh);
        });

        // Add edges with rounded appearance
        const boxGeometry = new THREE.BoxGeometry(size, size, size);
        const edges = new THREE.EdgesGeometry(boxGeometry);
        const edgeMaterial = new THREE.LineBasicMaterial({ color: this.colors.cubeBorder, linewidth: 2 });
        const edgeLines = new THREE.LineSegments(edges, edgeMaterial);
        this.cubeGroup.add(edgeLines);

        this.scene.add(this.cubeGroup);
    }

    createFaceTexture(text, isHovered = false) {
        const canvas = document.createElement('canvas');
        const res = 192;
        canvas.width = res;
        canvas.height = res;
        const ctx = canvas.getContext('2d');
        const radius = 16;

        // Rounded rectangle background
        ctx.beginPath();
        ctx.moveTo(radius, 0);
        ctx.lineTo(res - radius, 0);
        ctx.quadraticCurveTo(res, 0, res, radius);
        ctx.lineTo(res, res - radius);
        ctx.quadraticCurveTo(res, res, res - radius, res);
        ctx.lineTo(radius, res);
        ctx.quadraticCurveTo(0, res, 0, res - radius);
        ctx.lineTo(0, radius);
        ctx.quadraticCurveTo(0, 0, radius, 0);
        ctx.closePath();

        ctx.fillStyle = isHovered ? this.colors.cubeHover : this.colors.cubeBase;
        ctx.fill();

        // Border
        ctx.strokeStyle = this.colors.cubeBorder;
        ctx.lineWidth = 3;
        ctx.stroke();

        // Text
        ctx.font = 'bold 28px system-ui, -apple-system, sans-serif';
        ctx.fillStyle = this.colors.text;
        ctx.textAlign = 'center';
        ctx.textBaseline = 'middle';
        ctx.fillText(text, res / 2, res / 2);

        const texture = new THREE.CanvasTexture(canvas);
        texture.needsUpdate = true;
        return texture;
    }

    createAxes() {
        this.axesGroup = new THREE.Group();
        this.axesGroup.userData.nonInteractive = true;

        const length = 1.6;
        const radius = 0.025;

        this.createAxis('X', this.colors.axisX, new THREE.Vector3(1, 0, 0), length, radius);
        this.createAxis('Y', this.colors.axisY, new THREE.Vector3(0, 1, 0), length, radius);
        this.createAxis('Z', this.colors.axisZ, new THREE.Vector3(0, 0, 1), length, radius);

        this.scene.add(this.axesGroup);
    }

    createAxis(label, color, direction, length, radius) {
        const material = new THREE.MeshBasicMaterial({ color });

        // Line
        const lineGeom = new THREE.CylinderGeometry(radius, radius, length, 8);
        const line = new THREE.Mesh(lineGeom, material);

        if (direction.x === 1) {
            line.rotation.z = -Math.PI / 2;
            line.position.x = length / 2;
        } else if (direction.y === 1) {
            line.position.y = length / 2;
        } else {
            line.rotation.x = Math.PI / 2;
            line.position.z = length / 2;
        }
        this.axesGroup.add(line);

        // Arrow tip
        const coneGeom = new THREE.ConeGeometry(radius * 3, radius * 8, 8);
        const cone = new THREE.Mesh(coneGeom, material);

        if (direction.x === 1) {
            cone.rotation.z = -Math.PI / 2;
            cone.position.x = length + radius * 4;
        } else if (direction.y === 1) {
            cone.position.y = length + radius * 4;
        } else {
            cone.rotation.x = Math.PI / 2;
            cone.position.z = length + radius * 4;
        }
        this.axesGroup.add(cone);

        // Label
        const labelSprite = this.createAxisLabel(label, color);
        if (direction.x === 1) labelSprite.position.x = length + 0.35;
        else if (direction.y === 1) labelSprite.position.y = length + 0.35;
        else labelSprite.position.z = length + 0.35;
        this.axesGroup.add(labelSprite);
    }

    createAxisLabel(text, color) {
        const canvas = document.createElement('canvas');
        canvas.width = 64;
        canvas.height = 64;
        const ctx = canvas.getContext('2d');

        ctx.clearRect(0, 0, 64, 64);
        ctx.font = 'bold 44px system-ui, sans-serif';
        ctx.fillStyle = '#' + color.toString(16).padStart(6, '0');
        ctx.textAlign = 'center';
        ctx.textBaseline = 'middle';
        ctx.fillText(text, 32, 32);

        const texture = new THREE.CanvasTexture(canvas);
        const material = new THREE.SpriteMaterial({ map: texture, transparent: true });
        const sprite = new THREE.Sprite(material);
        sprite.scale.set(0.3, 0.3, 1);
        sprite.userData.nonInteractive = true;

        return sprite;
    }

    /**
     * Update face labels from JSON orientation data.
     * Extracts orientation info from the loaded model JSON.
     *
     * @param {object} json - The full JSON model data containing faces.bodies[].faces[].orientation
     */
    updateOrientationsFromJson(json) {
        if (!json?.faces?.bodies) {
            return;
        }

        // Build orientation-to-normal map from JSON
        const orientationMap = {};
        for (const body of json.faces.bodies) {
            for (const face of (body.faces || [])) {
                if (face.orientation && face.normal) {
                    const key = face.orientation.toLowerCase();
                    if (!orientationMap[key]) {
                        orientationMap[key] = {
                            x: face.normal.x,
                            y: face.normal.y,
                            z: face.normal.z
                        };
                    }
                }
            }
        }

        if (Object.keys(orientationMap).length === 0) {
            return;
        }

        console.log('[NavigationCube] Orientation data from JSON:', orientationMap);

        // Update each cube face normal from the JSON data
        this.cubeFaces.forEach(mesh => {
            const key = mesh.userData.key;
            if (orientationMap[key]) {
                const n = orientationMap[key];
                mesh.userData.normal.set(n.x, n.y, n.z).normalize();
                console.log(`[NavigationCube] Updated face "${key}" normal to [${n.x}, ${n.y}, ${n.z}]`);
            }
        });

        this.render();
    }

    /**
     * Update face labels with custom names.
     *
     * @param {object} labels - Map of face key to label, e.g. { front: 'Avant', top: 'Dessus' }
     */
    updateFaceLabels(labels) {
        Object.assign(this.faceLabels, labels);

        this.cubeFaces.forEach(mesh => {
            const key = mesh.userData.key;
            if (labels[key]) {
                const newLabel = labels[key];
                mesh.userData.name = newLabel;
                const texture = this.createFaceTexture(newLabel);
                mesh.userData.originalTexture = texture;
                if (!mesh.userData.isHovered) {
                    mesh.material.map = texture;
                    mesh.material.needsUpdate = true;
                }
            }
        });

        this.render();
    }

    onMouseMove(event) {
        const rect = this.container.getBoundingClientRect();
        this.mouse.x = ((event.clientX - rect.left) / rect.width) * 2 - 1;
        this.mouse.y = -((event.clientY - rect.top) / rect.height) * 2 + 1;

        this.raycaster.setFromCamera(this.mouse, this.camera);

        // Reset previous hover
        if (this.hoveredObject && this.hoveredObject.userData.isHovered) {
            this.hoveredObject.material.map = this.hoveredObject.userData.originalTexture;
            this.hoveredObject.material.needsUpdate = true;
            this.hoveredObject.userData.isHovered = false;
            this.hoveredObject = null;
        }

        this.container.style.cursor = 'default';

        // Check faces
        const faceIntersects = this.raycaster.intersectObjects(this.cubeFaces);
        if (faceIntersects.length > 0) {
            this.hoveredObject = faceIntersects[0].object;
            const hoverTexture = this.createFaceTexture(this.hoveredObject.userData.name, true);
            this.hoveredObject.material.map = hoverTexture;
            this.hoveredObject.material.needsUpdate = true;
            this.hoveredObject.userData.isHovered = true;
            this.container.style.cursor = 'pointer';
        }

        this.render();
    }

    onMouseLeave() {
        if (this.hoveredObject && this.hoveredObject.userData.isHovered) {
            this.hoveredObject.material.map = this.hoveredObject.userData.originalTexture;
            this.hoveredObject.material.needsUpdate = true;
            this.hoveredObject.userData.isHovered = false;
            this.hoveredObject = null;
        }
        this.container.style.cursor = 'default';
        this.render();
    }

    onClick(event) {
        if (this.isAnimating) return;

        const rect = this.container.getBoundingClientRect();
        this.mouse.x = ((event.clientX - rect.left) / rect.width) * 2 - 1;
        this.mouse.y = -((event.clientY - rect.top) / rect.height) * 2 + 1;

        this.raycaster.setFromCamera(this.mouse, this.camera);

        const faceIntersects = this.raycaster.intersectObjects(this.cubeFaces);
        if (faceIntersects.length > 0) {
            const face = faceIntersects[0].object;
            this.orientToNormal(face.userData.normal);
        }
    }

    orientToNormal(normal) {
        const target = this.mainControls.target.clone();
        const distance = this.mainCamera.position.distanceTo(target);
        const newPos = normal.clone().multiplyScalar(distance).add(target);
        this.animateCameraTo(newPos, target);
    }

    animateCameraTo(position, target) {
        this.isAnimating = true;
        const duration = 400;
        const startPos = this.mainCamera.position.clone();
        const startTarget = this.mainControls.target.clone();
        const startTime = Date.now();

        const animate = () => {
            const t = Math.min((Date.now() - startTime) / duration, 1);
            const eased = 1 - Math.pow(1 - t, 3);

            this.mainCamera.position.lerpVectors(startPos, position, eased);
            this.mainControls.target.lerpVectors(startTarget, target, eased);
            this.mainControls.update();

            if (t < 1) {
                requestAnimationFrame(animate);
            } else {
                this.isAnimating = false;
            }
        };

        animate();
    }

    update() {
        const quaternion = this.mainCamera.quaternion.clone().invert();

        if (this.cubeGroup) this.cubeGroup.quaternion.copy(quaternion);
        if (this.axesGroup) this.axesGroup.quaternion.copy(quaternion);

        this.render();
    }

    render() {
        this.renderer.render(this.scene, this.camera);
    }

    setVisible(visible) {
        this.container.style.display = visible ? 'block' : 'none';
    }

    dispose() {
        this.container.removeEventListener('click', this.onClick);
        this.container.removeEventListener('mousemove', this.onMouseMove);
        this.container.removeEventListener('mouseleave', this.onMouseLeave);
        this.renderer.dispose();
    }
}
