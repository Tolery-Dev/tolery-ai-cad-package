/**
 * Mesh builders module for JsonModelViewer3D
 * Handles building Three.js meshes from different CAD JSON formats
 */
import * as THREE from "three";

/**
 * Build mesh from Onshape JSON format
 * @param {object} json - Onshape JSON data with faces.bodies structure
 * @param {THREE.Material[]} materials - Array of [base, hover, select] materials
 * @returns {THREE.Mesh|null} - The built mesh or null if invalid data
 */
export function buildMeshFromOnshapeJson(json, materials) {
    const bodies = json?.faces?.bodies;
    if (!Array.isArray(bodies)) return null;

    const pos = []; // non-indexed positions (x,y,z repeated)
    const groups = [];
    const faceGroups = [];
    const realFaceIds = [];

    for (let b = 0; b < bodies.length; b++) {
        const body = bodies[b];
        const faces = body?.faces || [];
        for (let f = 0; f < faces.length; f++) {
            const face = faces[f];
            const startBefore = pos.length;
            const faceId =
                face.id != null ? String(face.id) : `body${b}_face${f}`;

            const facets = face?.facets || [];
            for (let k = 0; k < facets.length; k++) {
                const vtx = facets[k]?.vertices;
                if (!Array.isArray(vtx) || vtx.length < 3) continue;
                if (vtx.length === 3) {
                    // already a triangle
                    pos.push(
                        vtx[0].x,
                        vtx[0].y,
                        vtx[0].z,
                        vtx[1].x,
                        vtx[1].y,
                        vtx[1].z,
                        vtx[2].x,
                        vtx[2].y,
                        vtx[2].z,
                    );
                } else {
                    // fan triangulation
                    for (let i = 2; i < vtx.length; i++) {
                        const a = vtx[0],
                            bv = vtx[i - 1],
                            c = vtx[i];
                        pos.push(
                            a.x,
                            a.y,
                            a.z,
                            bv.x,
                            bv.y,
                            bv.z,
                            c.x,
                            c.y,
                            c.z,
                        );
                    }
                }
            }

            const addedFloats = pos.length - startBefore;
            const startIndex = startBefore / 3;
            const countIndex = addedFloats / 3;
            if (countIndex > 0) {
                groups.push({ start: startIndex, count: countIndex });
                faceGroups.push({
                    start: startIndex,
                    count: countIndex,
                    id: faceId,
                });
                realFaceIds.push(faceId);
            }
        }
    }

    if (!pos.length || !groups.length) return null;

    const geometry = new THREE.BufferGeometry();
    geometry.setAttribute(
        "position",
        new THREE.Float32BufferAttribute(pos, 3),
    );
    geometry.computeVertexNormals();
    geometry.groups = groups;

    const mesh = new THREE.Mesh(geometry, materials);
    mesh.userData.faceGroups = faceGroups;
    mesh.userData.realFaceIdsByGroup = realFaceIds;
    return mesh;
}

/**
 * Build mesh from FreeCad JSON format
 * @param {object} json - FreeCad JSON data with objects array structure
 * @param {THREE.Material[]} materials - Array of [base, hover, select] materials
 * @returns {THREE.Mesh|null} - The built mesh or null if invalid data
 */
export function buildMeshFromFreecadJson(json, materials) {
    const objects = json?.objects;
    if (!Array.isArray(objects)) return null;

    const positions = [];
    const groups = [];
    const faceGroups = [];
    const realFaceIds = [];
    let baseVertex = 0;

    for (let oi = 0; oi < objects.length; oi++) {
        const obj = objects[oi];
        const verts = obj?.vertices || [];
        const facets = obj?.facets || [];
        for (let v = 0; v < verts.length; v++) {
            positions.push(verts[v][0], verts[v][1], verts[v][2]);
        }

        for (let fi = 0; fi < facets.length; fi++) {
            const face = facets[fi];
            if (!Array.isArray(face) || face.length < 3) continue;
            const start = positions.length / 3;
            // build triangles into a temporary array and then map to non-indexed
            const triIndices = [];
            triIndices.push(
                baseVertex + face[0],
                baseVertex + face[1],
                baseVertex + face[2],
            );
            for (let k = 3; k < face.length; k++) {
                triIndices.push(
                    baseVertex + face[0],
                    baseVertex + face[k - 1],
                    baseVertex + face[k],
                );
            }

            // expand to non-indexed positions
            const tmp = [];
            for (let i = 0; i < triIndices.length; i++) {
                const vi = triIndices[i];
                const vx = json.objects[oi].vertices[vi - baseVertex];
                tmp.push(vx[0], vx[1], vx[2]);
            }
            const added = tmp.length / 3;
            // append tmp to positions end
            for (let i = 0; i < tmp.length; i++) {
                positions.push(tmp[i]);
            }

            groups.push({ start, count: added });
            const id = `freecad_obj${oi}_facet${fi}`;
            faceGroups.push({ start, count: added, id });
            realFaceIds.push(id);
        }
        baseVertex += verts.length;
    }

    if (!positions.length || !groups.length) return null;

    const geometry = new THREE.BufferGeometry();
    geometry.setAttribute(
        "position",
        new THREE.Float32BufferAttribute(positions, 3),
    );
    geometry.computeVertexNormals();
    geometry.groups = groups;

    const mesh = new THREE.Mesh(geometry, materials);
    mesh.userData.faceGroups = faceGroups;
    mesh.userData.realFaceIdsByGroup = realFaceIds;
    return mesh;
}
