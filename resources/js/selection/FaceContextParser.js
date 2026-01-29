/**
 * FaceContextParser - Utility to parse face context patterns and generate chip HTML
 * Used to render face selection chips in chat messages
 */
export class FaceContextParser {
    static PATTERN = /\[FACE_CONTEXT:\s*(.+?)\]\]/g;

    static parse(content) {
        if (!content) return content;

        return content.replace(this.PATTERN, (match, faceContext) => {
            console.log("[FaceContextParser] Match found:", match);
            console.log("[FaceContextParser] Captured context:", faceContext);
            return this.createChipHTML(faceContext);
        });
    }

    static createChipHTML(faceContext) {
        // Extract face ID from context (e.g., "Face Selection: ID[JfB] ...")
        const idMatch = faceContext.match(/ID\[([^\]]+)\]/);
        console.log("[FaceContextParser] ID match:", idMatch);
        const faceId = idMatch ? idMatch[1] : "Unknown";
        const label = `Face ${faceId}`;
        console.log("[FaceContextParser] Final label:", label);

        // Create chip HTML identical to composer chips
        return `<span class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full border border-violet-200 bg-violet-50 text-violet-700 text-sm font-medium">
      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
      </svg>
      <span>${label}</span>
    </span>`;
    }
}
