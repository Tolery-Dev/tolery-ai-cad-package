/**
 * NavigationCube - Onshape-style navigation cube
 * Features:
 * - 6 labeled faces with rounded corners and semi-transparent background
 * - XYZ axis indicators with colored labels
 * - Click face to orient camera
 * - Drag cube to rotate the 3D view (like Onshape)
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

        // Drag state
        this.isDragging = false;
        this.dragStart = new THREE.Vector2();
        this.dragThreshold = 4; // px before drag starts

        // Default face labels (French)
        this.faceLabels = {
            front: 'Avant',
            rear: 'Arrière',
            right: 'Droite',
            left: 'Gauche',
            top: 'Dessus',
            bottom: 'Dessous'
        };

        // Colors - Onshape style
        this.colors = {
            cubeBase: 'rgba(243, 244, 246, 0.85)',
            cubeHover: 'rgba(196, 181, 253, 0.9)',
            cubeBorder: '#d1d5db',
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

        // Setup camera — vue iso depuis le coin RIGHT/FRONT/TOP du cube
        // pour qu'au repos on voie TOP, FRONT et RIGHT (convention CAO Z-up).
        // L'orientation up est explicitement Z-up, cohérente avec la scène principale.
        this.camera = new THREE.OrthographicCamera(-1.6, 1.6, 1.6, -1.6, 0.1, 20);
        this.camera.up.set(0, 0, 1);
        this.camera.position.set(3, -3, 3);
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

        // Bind events
        this.onMouseDown = this.onMouseDown.bind(this);
        this.onMouseMove = this.onMouseMove.bind(this);
        this.onMouseUp = this.onMouseUp.bind(this);
        this.onMouseLeave = this.onMouseLeave.bind(this);

        this.container.addEventListener('mousedown', this.onMouseDown);
        this.container.addEventListener('mousemove', this.onMouseMove);
        this.container.addEventListener('mouseup', this.onMouseUp);
        this.container.addEventListener('mouseleave', this.onMouseLeave);

        this.render();
    }

    createCube() {
        this.cubeGroup = new THREE.Group();
        this.cubeFaces = [];

        const size = 0.9;
        const halfSize = size / 2;

        // Convention CAO Z-up (cohérente avec mesh-builders + DEFAULT_UP).
        // PlaneGeometry a sa face naturelle en +Z, donc TOP n'a pas besoin
        // de rotation et les autres faces sont tournées en conséquence.
        this.faceDefinitions = [
            { key: 'top',    pos: [0, 0, halfSize],   rot: [0, 0, 0],            normal: [0, 0, 1] },
            { key: 'bottom', pos: [0, 0, -halfSize],  rot: [Math.PI, 0, 0],      normal: [0, 0, -1] },
            { key: 'front',  pos: [0, -halfSize, 0],  rot: [Math.PI / 2, 0, 0],  normal: [0, -1, 0] },
            { key: 'rear',   pos: [0, halfSize, 0],   rot: [-Math.PI / 2, 0, Math.PI], normal: [0, 1, 0] },
            { key: 'right',  pos: [halfSize, 0, 0],   rot: [0, Math.PI / 2, Math.PI / 2], normal: [1, 0, 0] },
            { key: 'left',   pos: [-halfSize, 0, 0],  rot: [0, -Math.PI / 2, -Math.PI / 2], normal: [-1, 0, 0] },
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

        // Rounded edges
        const boxGeometry = new THREE.BoxGeometry(size, size, size);
        const edges = new THREE.EdgesGeometry(boxGeometry);
        const edgeMaterial = new THREE.LineBasicMaterial({ color: this.colors.cubeBorder, linewidth: 2 });
        const edgeLines = new THREE.LineSegments(edges, edgeMaterial);
        this.cubeGroup.add(edgeLines);

        this.scene.add(this.cubeGroup);
    }

    createFaceTexture(text, isHovered = false) {
        const canvas = document.createElement('canvas');
        const res = 256;
        canvas.width = res;
        canvas.height = res;
        const ctx = canvas.getContext('2d');
        const radius = 32; // more rounded corners

        // Rounded rectangle
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

        // Semi-transparent fill
        ctx.fillStyle = isHovered ? this.colors.cubeHover : this.colors.cubeBase;
        ctx.fill();

        // Subtle border
        ctx.strokeStyle = this.colors.cubeBorder;
        ctx.lineWidth = 2;
        ctx.stroke();

        // Text
        ctx.font = 'bold 32px system-ui, -apple-system, sans-serif';
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

        const length = 1.05;
        const radius = 0.02;

        // Position axes at bottom-left-front corner of cube (Z-up convention),
        // just outside the cube so the X/Y/Z labels emerge clearly past the
        // cube edges (similar to the DFM viewer orientation widget).
        this.axesGroup.position.set(-0.55, -0.55, -0.55);

        this.createAxis('X', this.colors.axisX, new THREE.Vector3(1, 0, 0), length, radius);
        this.createAxis('Y', this.colors.axisY, new THREE.Vector3(0, 1, 0), length, radius);
        this.createAxis('Z', this.colors.axisZ, new THREE.Vector3(0, 0, 1), length, radius);

        this.scene.add(this.axesGroup);
    }

    createAxis(label, color, direction, length, radius) {
        const material = new THREE.MeshBasicMaterial({ color });

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

    // --- Drag-to-rotate: rotate the main camera by dragging on the cube ---

    onMouseDown(event) {
        this.dragStart.set(event.clientX, event.clientY);
        this.isDragging = false;
        this._mouseDownTime = Date.now();
    }

    onMouseMove(event) {
        const rect = this.container.getBoundingClientRect();
        this.mouse.x = ((event.clientX - rect.left) / rect.width) * 2 - 1;
        this.mouse.y = -((event.clientY - rect.top) / rect.height) * 2 + 1;

        // Handle drag rotation
        if (event.buttons === 1) {
            const dx = event.clientX - this.dragStart.x;
            const dy = event.clientY - this.dragStart.y;

            if (!this.isDragging && (Math.abs(dx) > this.dragThreshold || Math.abs(dy) > this.dragThreshold)) {
                this.isDragging = true;
            }

            if (this.isDragging) {
                this.container.style.cursor = 'grabbing';
                this.rotateCameraByDelta(dx, dy);
                this.dragStart.set(event.clientX, event.clientY);
                return;
            }
        }

        // Hover detection
        this.raycaster.setFromCamera(this.mouse, this.camera);

        if (this.hoveredObject && this.hoveredObject.userData.isHovered) {
            this.hoveredObject.material.map = this.hoveredObject.userData.originalTexture;
            this.hoveredObject.material.needsUpdate = true;
            this.hoveredObject.userData.isHovered = false;
            this.hoveredObject = null;
        }

        this.container.style.cursor = 'grab';

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

    onMouseUp(event) {
        if (this.isDragging) {
            this.isDragging = false;
            this.container.style.cursor = 'grab';
            return;
        }

        // Short click = face snap
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

    onMouseLeave() {
        this.isDragging = false;
        if (this.hoveredObject && this.hoveredObject.userData.isHovered) {
            this.hoveredObject.material.map = this.hoveredObject.userData.originalTexture;
            this.hoveredObject.material.needsUpdate = true;
            this.hoveredObject.userData.isHovered = false;
            this.hoveredObject = null;
        }
        this.container.style.cursor = 'grab';
        this.render();
    }

    /**
     * Rotate the main camera around its orbit target by pixel delta.
     * Implémentation par quaternions pour fonctionner correctement quel que
     * soit le up de la caméra (Z-up dans notre cas). Le drag horizontal
     * tourne autour de l'axe up mondial, le drag vertical autour de l'axe
     * "right" local de la caméra. Le sens correspond à celui qu'on observe
     * dans le viewer (drag right = la pièce tourne vers la droite).
     */
    rotateCameraByDelta(dx, dy) {
        const sensitivity = 0.008;
        const cam = this.mainCamera;
        const target = this.mainControls.target;
        const offset = cam.position.clone().sub(target);

        // Axe up mondial pour la rotation horizontale (yaw)
        const up = cam.up.clone().normalize();
        // Axe right local de la caméra pour la rotation verticale (pitch)
        const forward = target.clone().sub(cam.position).normalize();
        const right = new THREE.Vector3().crossVectors(forward, up).normalize();

        const yaw = new THREE.Quaternion().setFromAxisAngle(up, -dx * sensitivity);
        const pitch = new THREE.Quaternion().setFromAxisAngle(right, -dy * sensitivity);

        offset.applyQuaternion(yaw).applyQuaternion(pitch);

        // Clamp pour éviter de passer la pièce à l'envers : on contraint
        // l'angle entre l'offset et l'axe up à rester entre ~5° et ~175°.
        const cosAngle = offset.clone().normalize().dot(up);
        if (cosAngle > 0.997 || cosAngle < -0.997) {
            return; // refuse la rotation qui collerait au pôle
        }

        cam.position.copy(target).add(offset);
        cam.lookAt(target);
        this.mainControls.update();
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
        this.container.removeEventListener('mousedown', this.onMouseDown);
        this.container.removeEventListener('mousemove', this.onMouseMove);
        this.container.removeEventListener('mouseup', this.onMouseUp);
        this.container.removeEventListener('mouseleave', this.onMouseLeave);
        this.renderer.dispose();
    }
}
