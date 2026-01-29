/**
 * Feature detection module for JsonModelViewer3D
 * Handles geometric analysis and feature type detection
 */
import * as THREE from "three";

/**
 * Find feature data for a given face ID from FreeCad features array
 * @param {Array} features - Array of features from FreeCad JSON
 * @param {string} faceId - The face ID to search for (e.g., "JfG", "JfA")
 * @returns {object|null} - The feature object if found, null otherwise
 */
export function getFeatureForFaceId(features, faceId) {
    if (!features || !Array.isArray(features)) {
        return null;
    }

    for (const feature of features) {
        // Check root level face_ids
        if (
            Array.isArray(feature.face_ids) &&
            feature.face_ids.includes(faceId)
        ) {
            return feature;
        }
        // Check root level edge_ids
        if (
            Array.isArray(feature.edge_ids) &&
            feature.edge_ids.includes(faceId)
        ) {
            return feature;
        }
        // Check nested structures for bending features (inner.face_ids, outer.face_ids)
        if (
            feature.inner &&
            Array.isArray(feature.inner.face_ids) &&
            feature.inner.face_ids.includes(faceId)
        ) {
            return feature;
        }
        if (
            feature.outer &&
            Array.isArray(feature.outer.face_ids) &&
            feature.outer.face_ids.includes(faceId)
        ) {
            return feature;
        }
    }

    return null;
}

/**
 * Get human-readable display type for a feature
 * @param {object} feature - Feature object from FreeCad JSON
 * @returns {string} - Display name
 */
export function getFeatureDisplayType(feature) {
    if (!feature || !feature.type) return "Feature";

    // Special handling for holes - include thread info if available
    if (feature.type === "hole") {
        // Check both subtype and thread property to detect threaded holes
        // Thread property contains the designation (e.g., "M3", "M4") or null
        const isThreaded =
            feature.subtype === "threaded" ||
            feature.subtype === "tapped" ||
            (feature.thread !== null &&
                feature.thread !== undefined &&
                feature.thread !== "");

        if (isThreaded) {
            // Build thread designation (e.g., "M3", "M4", "M5")
            let threadInfo = "Taraudage";
            if (feature.thread) {
                // If thread property exists with a value (e.g., "M3", "M4")
                threadInfo += ` ${feature.thread}`;
            } else if (feature.diameter) {
                // Infer metric thread from diameter (M3 = 3mm, M4 = 4mm, etc.)
                const d = parseFloat(feature.diameter);
                if (!isNaN(d)) {
                    // Standard metric thread diameters
                    const standardThreads = [
                        2, 2.5, 3, 4, 5, 6, 8, 10, 12, 14, 16, 18, 20,
                    ];
                    const closest = standardThreads.reduce((prev, curr) =>
                        Math.abs(curr - d) < Math.abs(prev - d) ? curr : prev,
                    );
                    if (Math.abs(closest - d) < 0.5) {
                        threadInfo += ` M${closest}`;
                    }
                }
            }
            return threadInfo;
        }
        return "Perçage";
    }

    // Special handling for fillets - include radius if available
    if (feature.type === "fillet") {
        if (feature.radius !== undefined && feature.radius !== null) {
            return `Congé R${feature.radius}`;
        }
        return "Congé";
    }

    // Special handling for bending - include radius info if available
    if (feature.type === "bending") {
        const innerRadius = feature.inner?.radius;
        if (innerRadius !== undefined && innerRadius !== null) {
            return `Pliage R${innerRadius}`;
        }
        return "Pliage";
    }

    const typeMap = {
        countersink: "Fraisage",
        chamfer: "Chanfrein",
        slot: "Rainure",
        box: "Face", // Face plane (from FreeCad API)
        oblong: "Oblong", // Oblong hole (slot with rounded ends)
        rectangular: "Face rectangulaire", // Rectangular face
        square: "Face carrée", // Square face
    };

    return typeMap[feature.type] || feature.type;
}

/**
 * Compute face normals from vertices
 * @param {THREE.Vector3[]} vertices - Array of vertices
 * @returns {THREE.Vector3[]} - Array of normal vectors
 */
export function computeFaceNormals(vertices) {
    const normals = [];
    for (let i = 0; i < vertices.length - 2; i += 3) {
        const a = vertices[i];
        const b = vertices[i + 1];
        const c = vertices[i + 2];

        const ab = new THREE.Vector3().subVectors(b, a);
        const ac = new THREE.Vector3().subVectors(c, a);
        const normal = new THREE.Vector3().crossVectors(ab, ac).normalize();

        normals.push(normal);
    }
    return normals;
}

/**
 * Compute variation in normals (used to detect curved surfaces)
 * @param {THREE.Vector3[]} normals - Array of normal vectors
 * @returns {number} - Average deviation from mean normal
 */
export function computeNormalVariation(normals) {
    if (normals.length === 0) return 0;

    const avgNormal = new THREE.Vector3();
    normals.forEach((n) => avgNormal.add(n));
    avgNormal.divideScalar(normals.length).normalize();

    let totalDeviation = 0;
    normals.forEach((n) => {
        const angle = Math.acos(Math.max(-1, Math.min(1, n.dot(avgNormal))));
        totalDeviation += angle;
    });

    return totalDeviation / normals.length;
}

/**
 * Compute bounding box from vertices
 * @param {THREE.Vector3[]} vertices - Array of vertices
 * @returns {{min: THREE.Vector3, max: THREE.Vector3}} - Bounding box
 */
export function computeBoundingBox(vertices) {
    const bbox = {
        min: new THREE.Vector3(Infinity, Infinity, Infinity),
        max: new THREE.Vector3(-Infinity, -Infinity, -Infinity),
    };

    vertices.forEach((v) => {
        bbox.min.x = Math.min(bbox.min.x, v.x);
        bbox.min.y = Math.min(bbox.min.y, v.y);
        bbox.min.z = Math.min(bbox.min.z, v.z);
        bbox.max.x = Math.max(bbox.max.x, v.x);
        bbox.max.y = Math.max(bbox.max.y, v.y);
        bbox.max.z = Math.max(bbox.max.z, v.z);
    });

    return bbox;
}

/**
 * Compute angular span of vertices around cylinder axis
 * @param {THREE.Vector3[]} vertices - Array of vertices
 * @param {{min: THREE.Vector3, max: THREE.Vector3}} bbox - Bounding box
 * @returns {number} - Angular span in degrees
 */
export function computeAngularSpan(vertices, bbox) {
    const center = new THREE.Vector3(
        (bbox.min.x + bbox.max.x) / 2,
        (bbox.min.y + bbox.max.y) / 2,
        (bbox.min.z + bbox.max.z) / 2,
    );

    const dims = {
        x: bbox.max.x - bbox.min.x,
        y: bbox.max.y - bbox.min.y,
        z: bbox.max.z - bbox.min.z,
    };
    const sorted = [dims.x, dims.y, dims.z].sort((a, b) => a - b);

    let axisIndex = 0;
    if (sorted[2] === dims.y) axisIndex = 1;
    else if (sorted[2] === dims.z) axisIndex = 2;

    const angles = vertices.map((v) => {
        let dx, dy;
        if (axisIndex === 0) {
            dy = v.y - center.y;
            dx = v.z - center.z;
        } else if (axisIndex === 1) {
            dy = v.x - center.x;
            dx = v.z - center.z;
        } else {
            dy = v.x - center.x;
            dx = v.y - center.y;
        }
        return Math.atan2(dy, dx);
    });

    const minAngle = Math.min(...angles);
    const maxAngle = Math.max(...angles);
    let span = (maxAngle - minAngle) * (180 / Math.PI);

    if (span < 0) span += 360;
    if (span > 360) span = 360;

    return span;
}

/**
 * Detect thread pattern in vertices (regular angular distribution)
 * @param {THREE.Vector3[]} vertices - Array of vertices
 * @returns {boolean} - True if thread pattern detected
 */
export function detectThreadPattern(vertices) {
    if (vertices.length < 100) return false;

    const bbox = computeBoundingBox(vertices);
    const center = new THREE.Vector3(
        (bbox.min.x + bbox.max.x) / 2,
        (bbox.min.y + bbox.max.y) / 2,
        (bbox.min.z + bbox.max.z) / 2,
    );

    const angles = vertices
        .map((v) => {
            const dx = v.x - center.x;
            const dy = v.y - center.y;
            return Math.atan2(dy, dx);
        })
        .sort((a, b) => a - b);

    const diffs = [];
    for (let i = 1; i < angles.length; i++) {
        diffs.push(angles[i] - angles[i - 1]);
    }

    const avgDiff = diffs.reduce((a, b) => a + b, 0) / diffs.length;
    const variance =
        diffs.reduce((sum, d) => sum + Math.pow(d - avgDiff, 2), 0) /
        diffs.length;

    return variance < 0.01; // Regular pattern = thread
}

/**
 * Detect face type from geometry analysis
 * @param {object} faceGroup - Face group with start and count
 * @param {THREE.BufferGeometry} geometry - The geometry
 * @returns {string} - Face type: 'planar', 'hole', 'thread', 'fillet', 'cylindrical'
 */
export function detectFaceType(faceGroup, geometry) {
    const pos = geometry.getAttribute("position");
    const vertices = [];

    // Extract all vertices for this face
    for (let i = faceGroup.start; i < faceGroup.start + faceGroup.count; i++) {
        vertices.push(new THREE.Vector3(pos.getX(i), pos.getY(i), pos.getZ(i)));
    }

    // Compute normals to detect curvature
    const normals = computeFaceNormals(vertices);
    const normalVariation = computeNormalVariation(normals);

    // Compute bounding box
    const bbox = computeBoundingBox(vertices);
    const dimensions = {
        x: bbox.max.x - bbox.min.x,
        y: bbox.max.y - bbox.min.y,
        z: bbox.max.z - bbox.min.z,
    };

    // Sort dimensions to find patterns
    const sorted = [dimensions.x, dimensions.y, dimensions.z].sort(
        (a, b) => a - b,
    );

    // Debug logs
    console.log("🔍 Face Detection Debug:", {
        normalVariation: normalVariation.toFixed(4),
        dimensions: {
            x: dimensions.x.toFixed(2),
            y: dimensions.y.toFixed(2),
            z: dimensions.z.toFixed(2),
        },
        sorted: sorted.map((d) => d.toFixed(2)),
        vertexCount: vertices.length,
        triangleCount: normals.length,
    });

    // Check cylindrical characteristics
    const isCylindrical = normalVariation > 0.1;

    // For cylinders, check if any two dimensions are similar
    const diff01 = Math.abs(sorted[0] - sorted[1]);
    const diff12 = Math.abs(sorted[1] - sorted[2]);
    const diff02 = Math.abs(sorted[0] - sorted[2]);
    const minDiff = Math.min(diff01, diff12, diff02);

    // Two dimensions are similar if the smallest difference is much smaller than the largest
    const twoSimilarDims = minDiff < sorted[2] * 0.3; // 30% tolerance

    // Additional check: for very small features, be more lenient
    const isVerySmall = sorted[2] < 20;

    console.log("🎯 Detection criteria:", {
        isCylindrical,
        twoSimilarDims,
        diff01: diff01.toFixed(2),
        diff12: diff12.toFixed(2),
        diff02: diff02.toFixed(2),
        minDiff: minDiff.toFixed(2),
        maxDim: sorted[2].toFixed(2),
        isSmall: sorted[2] < 50,
        isVerySmall,
    });

    // For cylindrical faces, determine if it's a hole or fillet by checking angular span
    if (isCylindrical && twoSimilarDims) {
        const angularSpan = computeAngularSpan(vertices, bbox);
        console.log("🔄 Angular span:", angularSpan.toFixed(1) + "°");

        // Fillet: typically ~90° arc (quarter cylinder on an edge)
        // Hole: typically 180°-360° arc (half or full cylinder)
        const isFillet = angularSpan < 135;

        if (isFillet) {
            console.log("✅ Detected: fillet (angular span < 135°)");
            return "fillet";
        }

        // Small cylindrical face with large angular span = hole
        if (sorted[2] < 50) {
            const hasThreadPattern = detectThreadPattern(vertices);
            console.log(
                "✅ Detected:",
                hasThreadPattern ? "thread" : "hole",
            );
            return hasThreadPattern ? "thread" : "hole";
        }

        console.log("✅ Detected: cylindrical");
        return "cylindrical";
    }

    // Planar face (flat surface)
    if (normalVariation < 0.05) {
        console.log("✅ Detected: planar (low variation)");
        return "planar";
    }

    console.log("✅ Detected: planar (default)");
    return "planar"; // Default
}

/**
 * Compute metrics for a detected face type
 * @param {string} faceType - The face type
 * @param {THREE.Vector3[]} vertices - Array of vertices
 * @param {{min: THREE.Vector3, max: THREE.Vector3}} bbox - Bounding box
 * @param {number} area - Face area in mm²
 * @returns {object} - Metrics object with displayType and relevant measurements
 */
export function computeFaceMetrics(faceType, vertices, bbox, area) {
    const dimensions = {
        x: bbox.max.x - bbox.min.x,
        y: bbox.max.y - bbox.min.y,
        z: bbox.max.z - bbox.min.z,
    };

    const sorted = [dimensions.x, dimensions.y, dimensions.z].sort(
        (a, b) => a - b,
    );
    const centroid = {
        x: (bbox.min.x + bbox.max.x) / 2,
        y: (bbox.min.y + bbox.max.y) / 2,
        z: (bbox.min.z + bbox.max.z) / 2,
    };

    switch (faceType) {
        case "planar":
            return {
                displayType: "Face plane",
                length: sorted[2],
                width: sorted[1],
                thickness: sorted[0],
                area: area,
                centroid: centroid,
            };

        case "fillet":
            return {
                displayType: "Congé",
                radius: sorted[0] / 2,
                length: sorted[2],
                area: area,
                centroid: centroid,
            };

        case "cylindrical":
            return {
                displayType: "Cylindre",
                radius: sorted[0] / 2,
                diameter: sorted[0],
                depth: sorted[2],
                length: sorted[2],
                area: area,
                centroid: centroid,
            };

        case "hole":
        case "thread": {
            // For holes/threads, find the two similar dimensions (diameter) vs the outlier (depth)
            const diff01 = Math.abs(sorted[1] - sorted[0]);
            const diff12 = Math.abs(sorted[2] - sorted[1]);

            let holeDiameter, holeDepth;
            if (diff01 < diff12) {
                holeDiameter = (sorted[0] + sorted[1]) / 2;
                holeDepth = sorted[2];
            } else {
                holeDiameter = (sorted[1] + sorted[2]) / 2;
                holeDepth = sorted[0];
            }

            return {
                displayType: faceType === "thread" ? "Taraudage" : "Perçage",
                diameter: holeDiameter,
                depth: holeDepth,
                pitch: faceType === "thread" ? null : undefined,
                position: centroid,
                area: area,
            };
        }

        default:
            return {
                displayType: "Face",
                area: area,
                centroid: centroid,
            };
    }
}

/**
 * Find group index for a given face ID in mesh data
 * @param {string[]} realFaceIdsByGroup - Array of face IDs indexed by group
 * @param {string} faceId - The face ID to search for
 * @returns {number|null} - The group index if found, null otherwise
 */
export function getGroupIndexForFaceId(realFaceIdsByGroup, faceId) {
    if (!realFaceIdsByGroup) return null;
    for (let i = 0; i < realFaceIdsByGroup.length; i++) {
        if (realFaceIdsByGroup[i] === faceId) return i;
    }
    return null;
}

/**
 * Get all group indices for a feature (for multi-face features like oblongs, fillets, bending)
 * @param {object} feature - The feature object with face_ids or edge_ids array
 * @param {string[]} realFaceIdsByGroup - Array of face IDs indexed by group
 * @returns {number[]} - Array of group indices
 */
export function getGroupIndicesForFeature(feature, realFaceIdsByGroup) {
    if (!feature) return [];
    const indices = [];

    // Support face_ids (for oblongs, holes) and edge_ids (for fillets, chamfers)
    // Also support nested structures for bending (inner.face_ids, outer.face_ids)
    const allIds = [
        ...(Array.isArray(feature.face_ids) ? feature.face_ids : []),
        ...(Array.isArray(feature.edge_ids) ? feature.edge_ids : []),
        ...(feature.inner && Array.isArray(feature.inner.face_ids)
            ? feature.inner.face_ids
            : []),
        ...(feature.outer && Array.isArray(feature.outer.face_ids)
            ? feature.outer.face_ids
            : []),
    ];

    for (const faceId of allIds) {
        const idx = getGroupIndexForFaceId(realFaceIdsByGroup, faceId);
        if (idx !== null) indices.push(idx);
    }
    return indices;
}
