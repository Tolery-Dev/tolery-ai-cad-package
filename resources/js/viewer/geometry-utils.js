/**
 * Geometry utilities module for JsonModelViewer3D
 * Provides geometric calculations for meshes
 */
import * as THREE from "three";

/**
 * Calculate mesh volume using signed tetrahedron method
 * Works for both indexed and non-indexed geometries
 * @param {THREE.BufferGeometry} geometry - The geometry to calculate volume for
 * @returns {number} - Volume in cubic units (mm³)
 */
export function calculateMeshVolume(geometry) {
    if (!geometry || !geometry.attributes?.position) return 0;

    const pos = geometry.attributes.position;
    const index = geometry.index;
    let volume = 0;

    if (index) {
        // Indexed geometry
        for (let i = 0; i < index.count; i += 3) {
            const i0 = index.getX(i);
            const i1 = index.getX(i + 1);
            const i2 = index.getX(i + 2);

            const v0 = new THREE.Vector3(
                pos.getX(i0),
                pos.getY(i0),
                pos.getZ(i0),
            );
            const v1 = new THREE.Vector3(
                pos.getX(i1),
                pos.getY(i1),
                pos.getZ(i1),
            );
            const v2 = new THREE.Vector3(
                pos.getX(i2),
                pos.getY(i2),
                pos.getZ(i2),
            );

            // Signed volume of tetrahedron formed with origin
            volume += v0.dot(v1.clone().cross(v2)) / 6;
        }
    } else {
        // Non-indexed geometry
        for (let i = 0; i < pos.count; i += 3) {
            const v0 = new THREE.Vector3(pos.getX(i), pos.getY(i), pos.getZ(i));
            const v1 = new THREE.Vector3(
                pos.getX(i + 1),
                pos.getY(i + 1),
                pos.getZ(i + 1),
            );
            const v2 = new THREE.Vector3(
                pos.getX(i + 2),
                pos.getY(i + 2),
                pos.getZ(i + 2),
            );

            volume += v0.dot(v1.clone().cross(v2)) / 6;
        }
    }

    return Math.abs(volume); // Return absolute value (mm³)
}
