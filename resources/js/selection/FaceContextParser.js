/**
 * FaceContextParser - Utility to parse face/edge context patterns and generate chip HTML
 * Used to render face/edge selection chips in chat messages
 *
 * Supports:
 * - [FACE_CONTEXT: Face Selection: ID[...] Type[...] ...]
 * - [EDGE_CONTEXT: ID[...] Type[Linear Edge] ...]
 */
export class FaceContextParser {
    static FACE_PATTERN = /\[FACE_CONTEXT:\s*(.+?)\]\]/g;
    static EDGE_PATTERN = /\[EDGE_CONTEXT:\s*(.+?)\]\]/g;

    static parse(content) {
        if (!content) return content;

        // Parse face contexts
        var result = content.replace(this.FACE_PATTERN, function (match, faceContext) {
            return FaceContextParser.createFaceChipHTML(faceContext);
        });

        // Parse edge contexts
        result = result.replace(this.EDGE_PATTERN, function (match, edgeContext) {
            return FaceContextParser.createEdgeChipHTML(edgeContext);
        });

        return result;
    }

    static createFaceChipHTML(faceContext) {
        // Extract face ID from context (e.g., "Face Selection: ID[JfB] ...")
        var idMatch = faceContext.match(/ID\[([^\]]+)\]/);
        var faceId = idMatch ? idMatch[1] : "Unknown";

        // Extract type if available (e.g., "Type[plane (95%)]")
        var typeMatch = faceContext.match(/Type\[([^\]]+)\]/);
        var faceType = typeMatch ? typeMatch[1] : null;

        var label = "Face " + faceId;
        if (faceType && faceType !== "unknown") {
            label += " (" + faceType + ")";
        }

        return '<span class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full border border-violet-200 bg-violet-50 text-violet-700 text-sm font-medium">' +
            '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">' +
            '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>' +
            '</svg>' +
            '<span>' + label + '</span>' +
            '</span>';
    }

    static createEdgeChipHTML(edgeContext) {
        // Extract edge ID (e.g., "ID[edge_0_0_48_50_0_48]")
        var idMatch = edgeContext.match(/ID\[([^\]]+)\]/);
        var edgeId = idMatch ? idMatch[1] : "Unknown";

        // Extract length if available (e.g., "Length[50.0]")
        var lengthMatch = edgeContext.match(/Length\[([^\]]+)\]/);
        var edgeLength = lengthMatch ? lengthMatch[1] : null;

        var label = "Edge " + edgeId;
        if (edgeLength) {
            label += " (" + edgeLength + " mm)";
        }

        return '<span class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full border border-green-200 bg-green-50 text-green-700 text-sm font-medium">' +
            '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">' +
            '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 12h16"></path>' +
            '</svg>' +
            '<span>' + label + '</span>' +
            '</span>';
    }
}
