# ToleryCAD v0.7.6 Implementation Plan

## Overview

This version adds two user-requested features:
1. **Face Selection Display Enhancement**: Replace technical face context text with visual chips in posted messages
2. **Rotation Center Visual Indicator**: Show temporary visual feedback when rotation center changes via Ctrl+click

---

## Feature 1: Face Selection Chips in Messages

### Current State
- Visual chips appear in chat composer when faces are selected
- On submit, technical text is injected: `[FACE_CONTEXT: Face Selection: ID[JfB] Position[center(607.1, 20.0, 4.0)] BBox[Size(1000.0, 40.0, 0.0)] Area[39932.8 mm²]]`
- Messages display this raw technical text
- Backend receives and processes the full technical text (required for AI context)

### Goal
- Display visual chips in posted messages (identical to composer chips)
- Backend still receives full technical text (no changes to data sent to AI)
- Support multiple face selections in one message

### Implementation Approach

**Option A: Client-side parsing with Alpine.js** (Recommended)
- Parse message content on display using Alpine.js
- Replace `[FACE_CONTEXT: ...]` patterns with chips
- Keep original data unchanged in database
- No backend modifications needed

**Option B: Server-side transformation**
- Add parsing in Livewire component
- Transform content in `mapDbMessagesToArray()`
- Requires careful handling to preserve AI context

**Decision: Use Option A** - simpler, keeps display logic separate from data logic

### Technical Implementation

#### Step 1: Create Face Context Parser Utility (JavaScript)

**File**: `/Users/croustibat/Projects/TOLERY/repositories/tolery-ai-cad-package/resources/js/app.js`

**Add after FaceSelectionManager class** (around line 1260):

```javascript
/**
 * Utility to parse face context patterns and generate chip HTML
 */
class FaceContextParser {
    static PATTERN = /\[FACE_CONTEXT:\s*([^\]]+)\]/g;

    static parse(content) {
        if (!content) return content;

        return content.replace(this.PATTERN, (match, faceContext) => {
            return this.createChipHTML(faceContext);
        });
    }

    static createChipHTML(faceContext) {
        // Extract face ID from context (e.g., "Face Selection: ID[JfB] ...")
        const idMatch = faceContext.match(/ID\[([^\]]+)\]/);
        const faceId = idMatch ? idMatch[1] : 'Unknown';
        const label = `Face ${faceId}`;

        // Create chip HTML identical to composer chips
        return `<span class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full border border-violet-200 bg-violet-50 text-violet-700 text-sm font-medium">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
            </svg>
            <span>${label}</span>
        </span>`;
    }
}

// Export for use in Blade templates
window.FaceContextParser = FaceContextParser;
```

#### Step 2: Add Alpine.js Directive for Message Parsing

**File**: `/Users/croustibat/Projects/TOLERY/repositories/tolery-ai-cad-package/resources/views/livewire/partials/chat-messages.blade.php`

**Current code** (line 38-40):
```blade
<div class="{{ $msg['role'] === 'user' ? 'inline-block border border-gray-100 bg-gray-50' : 'inline-block bg-gray-100 text-gray-900' }} rounded-xl px-3 py-2">
    {!! nl2br(e($msg['content'] ?? '')) !!}
</div>
```

**Replace with**:
```blade
<div
    class="{{ $msg['role'] === 'user' ? 'inline-block border border-gray-100 bg-gray-50' : 'inline-block bg-gray-100 text-gray-900' }} rounded-xl px-3 py-2"
    x-data="{
        content: @js($msg['content'] ?? ''),
        parsedContent: ''
    }"
    x-init="parsedContent = window.FaceContextParser ? window.FaceContextParser.parse(content) : content"
    x-html="parsedContent.replace(/\n/g, '<br>')">
</div>
```

**Note**: We use `x-html` instead of `{!! !!}` to allow dynamic parsing while maintaining security through the parser.

#### Step 3: Add CSS for Inline Chips (if needed)

**File**: May need to add to `/Users/croustibat/Projects/TOLERY/repositories/tolery-ai-cad-package/resources/css/app.css` if chips don't render properly inline

```css
/* Ensure face chips render inline with text */
.inline-flex.rounded-full {
    display: inline-flex;
    vertical-align: middle;
}
```

---

## Feature 2: Rotation Center Visual Indicator

### Current State
- Ctrl+click to change rotation center is **already implemented** (user confirmed)
- No visual feedback showing where the rotation center is located
- Three.js viewer with OrbitControls manages camera rotation

### Goal
- Show temporary visual indicator when rotation center changes
- Indicator should appear on Ctrl+click and disappear after ~2 seconds
- Use a simple sphere or point helper in Three.js

### Implementation Approach

**Technical Requirements:**
1. Hook into existing Ctrl+click rotation center change
2. Create Three.js helper object (sphere/point)
3. Position it at `controls.target`
4. Animate appearance and removal
5. Clean up on model change or view reset

### Technical Implementation

#### Step 1: Find Existing Ctrl+Click Implementation

**Action**: Search for Ctrl+click rotation center logic in app.js

Need to locate where:
- Keyboard state is tracked (`ctrlPressed` or similar)
- `controls.target` is updated on click
- Event dispatching happens

#### Step 2: Add Rotation Center Indicator Class

**File**: `/Users/croustibat/Projects/TOLERY/repositories/tolery-ai-cad-package/resources/js/app.js`

**Add to JsonModelViewer3D class** (around line 70, in constructor):

```javascript
// Rotation center indicator
this.rotationCenterIndicator = null;
this.rotationCenterTimeout = null;
```

**Add method to create indicator** (around line 680, near fitCamera):

```javascript
/**
 * Show temporary visual indicator at rotation center
 * @param {THREE.Vector3} position - Position to show indicator
 * @param {number} duration - How long to show (ms)
 */
showRotationCenterIndicator(position, duration = 2000) {
    // Remove existing indicator
    if (this.rotationCenterIndicator) {
        this.scene.remove(this.rotationCenterIndicator);
        this.rotationCenterIndicator.geometry.dispose();
        this.rotationCenterIndicator.material.dispose();
        this.rotationCenterIndicator = null;
    }

    // Clear existing timeout
    if (this.rotationCenterTimeout) {
        clearTimeout(this.rotationCenterTimeout);
    }

    // Create indicator sphere
    const geometry = new THREE.SphereGeometry(5, 16, 16); // 5mm radius
    const material = new THREE.MeshBasicMaterial({
        color: 0x8b5cf6, // Violet color matching UI
        opacity: 0.8,
        transparent: true,
        depthTest: false, // Always visible
        depthWrite: false
    });

    this.rotationCenterIndicator = new THREE.Mesh(geometry, material);
    this.rotationCenterIndicator.position.copy(position);
    this.scene.add(this.rotationCenterIndicator);

    // Animate fade in
    material.opacity = 0;
    const fadeIn = setInterval(() => {
        material.opacity += 0.1;
        if (material.opacity >= 0.8) {
            clearInterval(fadeIn);
        }
    }, 30);

    // Auto-remove after duration
    this.rotationCenterTimeout = setTimeout(() => {
        this.hideRotationCenterIndicator();
    }, duration);
}

/**
 * Hide and remove rotation center indicator
 */
hideRotationCenterIndicator() {
    if (!this.rotationCenterIndicator) return;

    const material = this.rotationCenterIndicator.material;

    // Animate fade out
    const fadeOut = setInterval(() => {
        material.opacity -= 0.1;
        if (material.opacity <= 0) {
            clearInterval(fadeOut);
            this.scene.remove(this.rotationCenterIndicator);
            this.rotationCenterIndicator.geometry.dispose();
            material.dispose();
            this.rotationCenterIndicator = null;
        }
    }, 30);
}
```

#### Step 3: Hook Into Existing Ctrl+Click Handler

**Action**: Find where `controls.target` is updated on Ctrl+click

**Expected location**: In `onMouseUp` method or similar click handler

**Add indicator call after target update**:
```javascript
// After: this.controls.target.copy(newCenter);
this.showRotationCenterIndicator(newCenter);
```

#### Step 4: Clean Up Indicator on View Reset

**File**: Same file, in methods that reset the view

**In `fitCamera()` method** (line 674):
```javascript
// At start of method, before anything else
if (this.rotationCenterIndicator) {
    this.hideRotationCenterIndicator();
}
```

**In model loading** (around line 391):
```javascript
// After successful model load
if (this.rotationCenterIndicator) {
    this.hideRotationCenterIndicator();
}
```

---

## Implementation Order

### Phase 1: Face Selection Chips (Priority 1)
1. ✅ Explore codebase (completed)
2. Add `FaceContextParser` class to app.js
3. Update chat-messages.blade.php with Alpine.js parsing
4. Test with single face selection
5. Test with multiple face selections
6. Verify backend still receives full technical text
7. Test old messages without face context

### Phase 2: Rotation Center Indicator (Priority 2)
1. ✅ Explore codebase (completed)
2. Search for existing Ctrl+click rotation implementation
3. Add indicator methods to JsonModelViewer3D
4. Hook into existing Ctrl+click handler
5. Add cleanup on view reset
6. Test indicator appearance/disappearance
7. Test on model change

### Phase 3: Testing & Quality
1. Run Pint for code formatting
2. Test both features together
3. Check for console errors
4. Verify performance (no memory leaks)
5. Test on different browsers

### Phase 4: Documentation & Commit
1. Update package version to 0.7.6
2. Write commit message
3. Push to feature branch
4. Create PR to main

---

## Files to Modify

### JavaScript
- `/Users/croustibat/Projects/TOLERY/repositories/tolery-ai-cad-package/resources/js/app.js`
  - Add `FaceContextParser` class (after line 1260)
  - Add rotation center indicator methods (in JsonModelViewer3D)
  - Hook indicator into Ctrl+click handler

### Blade Templates
- `/Users/croustibat/Projects/TOLERY/repositories/tolery-ai-cad-package/resources/views/livewire/partials/chat-messages.blade.php`
  - Update message content display (line 38-40)
  - Add Alpine.js parsing directive

### CSS (if needed)
- `/Users/croustibat/Projects/TOLERY/repositories/tolery-ai-cad-package/resources/css/app.css`
  - Add inline chip styles if rendering issues occur

---

## Testing Strategy

### Feature 1: Face Chips
1. **Test single face selection**:
   - Select a face in 3D viewer
   - Post message
   - Verify chip appears with correct ID
   - Check backend receives full `[FACE_CONTEXT: ...]` text

2. **Test multiple faces**:
   - Select 2-3 faces
   - Post message
   - Verify all chips appear
   - Verify spacing and layout

3. **Test mixed content**:
   - Type "Please modify " + select face + type " to be 10mm"
   - Verify text and chip render correctly together

4. **Test old messages**:
   - Load chat with existing messages without face context
   - Verify they display normally

### Feature 2: Rotation Center
1. **Test indicator appearance**:
   - Ctrl+click on model surface
   - Verify violet sphere appears at click point
   - Verify it fades in smoothly

2. **Test indicator removal**:
   - Wait 2 seconds
   - Verify indicator fades out and disappears

3. **Test cleanup**:
   - Show indicator
   - Click "Recentrer vue" button
   - Verify indicator disappears immediately

4. **Test model change**:
   - Show indicator
   - Load new model
   - Verify indicator is cleaned up

---

## Edge Cases & Considerations

### Face Chips
- **Security**: Use Alpine.js x-html with parsed content (not user input directly)
- **Performance**: Parsing happens client-side per message (acceptable for chat history size)
- **Backward compatibility**: Old messages without face context display normally
- **Multiple patterns**: Regex handles multiple `[FACE_CONTEXT: ...]` in one message
- **Malformed patterns**: Parser gracefully handles incomplete patterns

### Rotation Center
- **Memory leaks**: Dispose geometry and material on cleanup
- **Multiple rapid clicks**: Clear existing timeout before setting new one
- **Indicator visibility**: Use `depthTest: false` so it's always visible
- **Color choice**: Violet (#8b5cf6) matches UI theme
- **Size**: 5mm radius sphere is visible but not obstructive

---

## Success Criteria

### Feature 1
- ✅ Face chips display in posted messages with identical styling to composer
- ✅ Backend receives full technical text (verified in network tab or logs)
- ✅ Multiple face selections show multiple chips
- ✅ No console errors
- ✅ Old messages display correctly

### Feature 2
- ✅ Violet sphere appears at rotation center on Ctrl+click
- ✅ Indicator fades in smoothly
- ✅ Indicator disappears after 2 seconds
- ✅ Cleanup works on view reset and model change
- ✅ No memory leaks (check Three.js inspector)

---

## Notes

- Both features are display-only enhancements
- No database migrations required
- No breaking changes to existing functionality
- Maintains backward compatibility
- Uses existing infrastructure (Alpine.js, Three.js)
