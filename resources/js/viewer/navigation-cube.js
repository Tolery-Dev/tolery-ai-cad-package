/**
 * NavigationCube - Onshape-style navigation cube
 * Components:
 * - Main cube with 6 labeled faces (Front, Rear, Top, Bottom, Left, Right)
 * - XYZ axis indicators with colored labels
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

        // Colors - Onshape style (light gray cube)
        this.colors = {
            cubeBase: 0xe5e7eb,      // Light gray
            cubeHover: 0x93c5fd,     // Light blue hover
            cubeBorder: 0x9ca3af,    // Gray border
            text: 0x374151,          // Dark gray text
            axisX: 0xef4444,         // Red
            axisY: 0x22c55e,         // Green
            axisZ: 0x3b82f6          // Blue
        };

        // Setup renderer
        this.renderer = new THREE.WebGLRenderer({
            canvas: containerElement,
            alpha: true,
            antialias: true
        });
        this.renderer.setSize(150, 150);
        this.renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));
        this.renderer.setClearColor(0x000000, 0);

        // Setup scene
        this.scene = new THREE.Scene();

        // Setup camera
        this.camera = new THREE.OrthographicCamera(-2.2, 2.2, 2.2, -2.2, 0.1, 20);
        this.camera.position.set(0, 0, 5);
        this.camera.lookAt(0, 0, 0);

        // Lighting - brighter for Onshape style
        this.scene.add(new THREE.AmbientLight(0xffffff, 0.8));
        const dirLight = new THREE.DirectionalLight(0xffffff, 0.3);
        dirLight.position.set(2, 3, 4);
        this.scene.add(dirLight);

        // Create components (cube + axes only)
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

        const size = 1.2;
        const halfSize = size / 2;

        // Face definitions with proper labels
        const faces = [
            { name: 'Front', pos: [0, 0, halfSize], rot: [0, 0, 0], normal: [0, 0, 1] },
            { name: 'Rear', pos: [0, 0, -halfSize], rot: [0, Math.PI, 0], normal: [0, 0, -1] },
            { name: 'Right', pos: [halfSize, 0, 0], rot: [0, Math.PI/2, 0], normal: [1, 0, 0] },
            { name: 'Left', pos: [-halfSize, 0, 0], rot: [0, -Math.PI/2, 0], normal: [-1, 0, 0] },
            { name: 'Top', pos: [0, halfSize, 0], rot: [-Math.PI/2, 0, 0], normal: [0, 1, 0] },
            { name: 'Bottom', pos: [0, -halfSize, 0], rot: [Math.PI/2, 0, 0], normal: [0, -1, 0] }
        ];

        faces.forEach(face => {
            // Create textured face with label
            const texture = this.createFaceTexture(face.name);
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
                name: face.name,
                normal: new THREE.Vector3(...face.normal),
                originalTexture: texture,
                isHovered: false
            };

            this.cubeGroup.add(mesh);
            this.cubeFaces.push(mesh);
        });

        // Add edges with thicker lines
        const boxGeometry = new THREE.BoxGeometry(size, size, size);
        const edges = new THREE.EdgesGeometry(boxGeometry);
        const edgeMaterial = new THREE.LineBasicMaterial({ color: this.colors.cubeBorder, linewidth: 2 });
        const edgeLines = new THREE.LineSegments(edges, edgeMaterial);
        this.cubeGroup.add(edgeLines);

        this.scene.add(this.cubeGroup);
    }

    createFaceTexture(text, isHovered = false) {
        const canvas = document.createElement('canvas');
        canvas.width = 128;
        canvas.height = 128;
        const ctx = canvas.getContext('2d');

        // Background
        ctx.fillStyle = isHovered ? '#93c5fd' : '#e5e7eb';
        ctx.fillRect(0, 0, 128, 128);

        // Border
        ctx.strokeStyle = '#9ca3af';
        ctx.lineWidth = 3;
        ctx.strokeRect(2, 2, 124, 124);

        // Text
        ctx.font = 'bold 22px system-ui, -apple-system, sans-serif';
        ctx.fillStyle = '#374151';
        ctx.textAlign = 'center';
        ctx.textBaseline = 'middle';
        ctx.fillText(text, 64, 64);

        const texture = new THREE.CanvasTexture(canvas);
        texture.needsUpdate = true;
        return texture;
    }

    createAxes() {
        this.axesGroup = new THREE.Group();
        this.axesGroup.userData.nonInteractive = true;

        const length = 1.6;
        const radius = 0.02;

        // X axis (Red)
        this.createAxis('X', this.colors.axisX, new THREE.Vector3(1, 0, 0), length, radius);
        // Y axis (Green)
        this.createAxis('Y', this.colors.axisY, new THREE.Vector3(0, 1, 0), length, radius);
        // Z axis (Blue)
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
        if (direction.x === 1) labelSprite.position.x = length + 0.3;
        else if (direction.y === 1) labelSprite.position.y = length + 0.3;
        else labelSprite.position.z = length + 0.3;
        this.axesGroup.add(labelSprite);
    }

    createAxisLabel(text, color) {
        const canvas = document.createElement('canvas');
        canvas.width = 64;
        canvas.height = 64;
        const ctx = canvas.getContext('2d');

        ctx.clearRect(0, 0, 64, 64);
        ctx.font = 'bold 40px system-ui, sans-serif';
        ctx.fillStyle = '#' + color.toString(16).padStart(6, '0');
        ctx.textAlign = 'center';
        ctx.textBaseline = 'middle';
        ctx.fillText(text, 32, 32);

        const texture = new THREE.CanvasTexture(canvas);
        const material = new THREE.SpriteMaterial({ map: texture, transparent: true });
        const sprite = new THREE.Sprite(material);
        sprite.scale.set(0.25, 0.25, 1);
        sprite.userData.nonInteractive = true;

        return sprite;
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

        // Check faces
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
