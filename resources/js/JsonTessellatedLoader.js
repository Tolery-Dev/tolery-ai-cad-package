// JsonTessellatedLoader.js
// Plain JS helper to build a THREE.Mesh from Onshape-like tessellated JSON.

import * as THREE from 'three';

export default class JsonTessellatedLoader {
  /**
   * @param {Object} opts
   *  - units: 'mm' | 'm' (default 'mm')
   *  - recenter: boolean (default true)
   *  - autoscale: boolean (default true) // if numbers are extremely small/large
   *  - fixWinding: boolean (default true) // flips triangle if normal points “backwards” vs running average
   *  - mergeTolerance: number (default 1e-5) // vertex de-dup (after unit scale)
   */
  constructor(opts = {}) {
    this.opts = Object.assign({
      units: 'mm',
      recenter: true,
      autoscale: true,
      fixWinding: true,
      mergeTolerance: 1e-5
    }, opts);
    this._scale = (this.opts.units === 'mm') ? 0.001 : 1.0; // mm → m
  }

  /**
   * Build a mesh from the given JSON object.
   * Returns { mesh, faceGroups, faceIdToGroupIndex }
   */
  parse(json) {
    const positions = [];
    const indices = [];
    const faceGroups = [];       // { id, start, count }
    const faceIdToGroupIndex = new Map();

    // Collect triangles per face
    let triStart = 0;
    const bodies = (json.faces && Array.isArray(json.faces.bodies)) ? json.faces.bodies : [];
    const addTriangle = (a, b, c) => {
      const idxBase = positions.length / 3;
      positions.push(a.x, a.y, a.z, b.x, b.y, b.z, c.x, c.y, c.z);
      indices.push(idxBase, idxBase + 1, idxBase + 2);
    };

    const pushFaceGroup = (faceId, triCount) => {
      if (triCount <= 0) return;
      let id = faceId || 'face';
      // ensure unique group id (files 19083119 repeat ids)
      let unique = id, i = 1;
      while (faceGroups.some(g => g.id === unique)) {
        unique = `${id}#${++i}`;
      }
      const start = triStart * 3;     // indices are triangles; group uses index span (in triangles)
      const count = triCount * 3;
      faceGroups.push({ id: unique, start, count });
      faceIdToGroupIndex.set(unique, faceGroups.length - 1);
      triStart += triCount;
    };

    // helper numeric utils
    const scaleVec = (v) => new THREE.Vector3(v.x * this._scale, v.y * this._scale, v.z * this._scale);
    const triArea = (a, b, c) => {
      const ab = new THREE.Vector3().subVectors(b, a);
      const ac = new THREE.Vector3().subVectors(c, a);
      return new THREE.Vector3().crossVectors(ab, ac).length() * 0.5;
    };

    // When only facetPoints/indices exist (indexed).
    const addIndexedFace = (face, facetPoints, inds) => {
      if (!Array.isArray(inds)) return 0;
      let faceTris = 0;
      for (let i = 0; i + 2 < inds.length; i += 3) {
        const a = scaleVec(facetPoints[inds[i]]);
        const b = scaleVec(facetPoints[inds[i + 1]]);
        const c = scaleVec(facetPoints[inds[i + 2]]);
        if (this._addCleanTriangle(a, b, c, addTriangle)) faceTris++;
      }
      return faceTris;
    };

    // When each facet already has vertices (non-indexed).
    const addInlineFace = (face) => {
      let faceTris = 0;
      const facets = Array.isArray(face.facets) ? face.facets : [];
      // running normal for winding correction
      let avgN = new THREE.Vector3(0, 0, 1);
      for (const f of facets) {
        const vs = Array.isArray(f.vertices) ? f.vertices : [];
        if (vs.length !== 3) continue;
        let a = scaleVec(vs[0]), b = scaleVec(vs[1]), c = scaleVec(vs[2]);

        // skip degenerate / microscopic triangles
        if (!this._addCleanTriangle(a, b, c, (aa, bb, cc) => {
          // optional winding fix: orient_close_to avg normal
          if (this.opts.fixWinding) {
            const n = new THREE.Vector3().crossVectors(
              new THREE.Vector3().subVectors(bb, aa),
              new THREE.Vector3().subVectors(cc, aa)
            );
            // If average normal is near zero (first triangles), trust +Z.
            const ref = avgN.lengthSq() < 1e-12 ? new THREE.Vector3(0, 0, 1) : avgN.clone().normalize();
            if (n.dot(ref) < 0) { const tmp = bb; bb = cc; cc = tmp; n.multiplyScalar(-1); }
            avgN.add(n);
          }
          addTriangle(aa, bb, cc);
        })) continue;

        faceTris++;
      }
      return faceTris;
    };

    // Iterate bodies/faces
    for (const body of bodies) {
      // Try the indexed variant (rare in your failing files)
      const facetPoints = Array.isArray(body.facetPoints) ? body.facetPoints.map(p => ({ x: p.x, y: p.y, z: p.z })) : null;

      for (const face of (Array.isArray(body.faces) ? body.faces : [])) {
        const before = indices.length / 3;
        let added = 0;

        if (facetPoints && Array.isArray(face.indices) && face.indices.length) {
          added = addIndexedFace(face, facetPoints, face.indices);
        } else {
          added = addInlineFace(face);
        }

        const triCount = (indices.length / 3) - before;
        // Guard: if a face contributes nothing, we still keep going.
        pushFaceGroup(face.id, triCount);
      }
    }

    // Build BufferGeometry
    const geom = new THREE.BufferGeometry();
    // Optional autoscale: if extents are too tiny/huge, rescale to keep camera sane
    let box = new THREE.Box3();
    let posArr = new Float32Array(positions);
    geom.setAttribute('position', new THREE.BufferAttribute(posArr, 3));
    geom.setIndex(indices);

    // Merge nearly-duplicate vertices to avoid cracks
    this._weldVertices(geom, this.opts.mergeTolerance);

    // Recompute normals for consistent shading
    geom.computeVertexNormals();
    geom.computeBoundingBox();
    geom.computeBoundingSphere();

    box.copy(geom.boundingBox);

    // Optional recenter
    if (this.opts.recenter && box.isEmpty() === false) {
      const center = new THREE.Vector3();
      box.getCenter(center);
      geom.translate(-center.x, -center.y, -center.z);
      geom.computeBoundingBox(); // update
      geom.computeBoundingSphere();
    }

    // Optional autoscale (very small objects -> normalize to reasonable size)
    if (this.opts.autoscale && geom.boundingSphere) {
      const r = geom.boundingSphere.radius || 1;
      const target = 1.0; // meter-ish scene scale
      if (r > 0 && (r < 0.01 || r > 1000)) {
        const s = target / r;
        geom.scale(s, s, s);
        geom.computeBoundingBox();
        geom.computeBoundingSphere();
      }
    }

    // Make groups on geometry (used for picking / recolor per face)
    for (const g of faceGroups) {
      // THREE geometry groups are in indices units: start,count in indices
      geom.addGroup(g.start * 3, g.count * 3, 0);
    }

    const mat = new THREE.MeshStandardMaterial({
      color: 0x9ea3a8,
      metalness: 0.5,
      roughness: 0.5,
      side: THREE.DoubleSide // in case of mixed winding or open shells
    });

    const mesh = new THREE.Mesh(geom, mat);
    mesh.name = 'json-tessellated-mesh';
    mesh.userData.faceGroups = faceGroups;
    mesh.userData.faceIdToGroupIndex = faceIdToGroupIndex;

    return { mesh, faceGroups, faceIdToGroupIndex };
  }

  // --- helpers ---

  _addCleanTriangle(a, b, c, pushFn) {
    // drop degenerate triangles (area ~ 0)
    const area = (() => {
      const ab = new THREE.Vector3().subVectors(b, a);
      const ac = new THREE.Vector3().subVectors(c, a);
      return new THREE.Vector3().crossVectors(ab, ac).length() * 0.5;
    })();
    if (!isFinite(area) || area < 1e-12) return false;

    // NaN guard
    if (![a.x, a.y, a.z, b.x, b.y, b.z, c.x, c.y, c.z].every(Number.isFinite)) return false;

    pushFn(a, b, c);
    return true;
  }

  _weldVertices(geometry, tolerance = 1e-5) {
    // simple vertex weld using a hash grid
    const pos = geometry.getAttribute('position');
    const idx = geometry.getIndex();
    const hash = new Map();
    const newVerts = [];
    const remap = new Array(pos.count);
    const keyOf = (x, y, z) => {
      const kx = Math.round(x / tolerance);
      const ky = Math.round(y / tolerance);
      const kz = Math.round(z / tolerance);
      return `${kx},${ky},${kz}`;
    };

    for (let i = 0; i < pos.count; i++) {
      const x = pos.getX(i), y = pos.getY(i), z = pos.getZ(i);
      const key = keyOf(x, y, z);
      if (hash.has(key)) {
        remap[i] = hash.get(key);
      } else {
        const newIndex = newVerts.length / 3;
        newVerts.push(x, y, z);
        hash.set(key, newIndex);
        remap[i] = newIndex;
      }
    }

    const newIndex = new (idx ? idx.array.constructor : Uint32Array)(idx ? idx.count : (pos.count));
    if (idx) {
      for (let i = 0; i < idx.count; i++) newIndex[i] = remap[idx.getX(i)];
      geometry.setIndex(new THREE.BufferAttribute(newIndex, 1));
    } else {
      for (let i = 0; i < newIndex.length; i++) newIndex[i] = remap[i];
      geometry.setIndex(new THREE.BufferAttribute(newIndex, 1));
    }
    geometry.setAttribute('position', new THREE.BufferAttribute(new Float32Array(newVerts), 3));
    geometry.deleteAttribute('normal'); // will recompute after
  }
}
