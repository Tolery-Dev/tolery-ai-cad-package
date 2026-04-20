/**
 * Screenshot module for JsonModelViewer3D
 * Handles capturing and sending screenshots of the 3D scene
 */

/**
 * Capture a screenshot of the 3D scene
 * @param {THREE.WebGLRenderer} renderer - The Three.js renderer
 * @param {THREE.Scene} scene - The Three.js scene
 * @param {THREE.Camera} camera - The Three.js camera
 * @param {HTMLElement} container - The container element
 * @param {number} width - Screenshot width (default: 800)
 * @param {number} height - Screenshot height (default: 800)
 * @returns {Promise<Blob>} - Promise resolving with the PNG blob
 */
export async function captureScreenshot(
    renderer,
    scene,
    camera,
    container,
    width = 800,
    height = 800,
) {
    return new Promise((resolve, reject) => {
        try {
            // Save current size
            const originalWidth = container.clientWidth;
            const originalHeight = container.clientHeight;
            const originalAspect = camera.aspect;

            // Temporarily resize the renderer
            camera.aspect = width / height;
            camera.updateProjectionMatrix();
            renderer.setSize(width, height);

            // Force a render
            renderer.render(scene, camera);

            // Capture the screenshot
            renderer.domElement.toBlob(
                (blob) => {
                    // Restore original size
                    camera.aspect = originalAspect;
                    camera.updateProjectionMatrix();
                    renderer.setSize(originalWidth, originalHeight);
                    renderer.render(scene, camera);

                    if (blob) {
                        resolve(blob);
                    } else {
                        reject(new Error("Failed to create blob from canvas"));
                    }
                },
                "image/png",
                0.95,
            );
        } catch (error) {
            console.error("[screenshot] Capture failed:", error);
            reject(error);
        }
    });
}

/**
 * Convert blob to base64 string
 * @param {Blob} blob - The blob to convert
 * @returns {Promise<string>} - Promise resolving with base64 string (without prefix)
 */
export function blobToBase64(blob) {
    return new Promise((resolve, reject) => {
        const reader = new FileReader();
        reader.onloadend = () => {
            const base64 = reader.result.split(",")[1]; // Remove data:image/png;base64, prefix
            resolve(base64);
        };
        reader.onerror = (error) => {
            console.error("[screenshot] Failed to read blob:", error);
            reject(error);
        };
        reader.readAsDataURL(blob);
    });
}

/**
 * Send screenshot to Livewire
 * @param {string} base64Data - Base64 encoded image data
 */
export function sendScreenshotToLivewire(base64Data) {
    if (window.Livewire) {
        Livewire.dispatch("saveClientScreenshot", {
            base64Data: base64Data,
        });
        console.log("[screenshot] Sent to Livewire");
    } else {
        console.warn("[screenshot] Livewire not available, screenshot not sent");
    }
}

/**
 * Capture and send screenshot to Livewire (convenience function)
 * @param {THREE.WebGLRenderer} renderer - The Three.js renderer
 * @param {THREE.Scene} scene - The Three.js scene
 * @param {THREE.Camera} camera - The Three.js camera
 * @param {HTMLElement} container - The container element
 * @param {number} width - Screenshot width (default: 800)
 * @param {number} height - Screenshot height (default: 800)
 */
export async function captureAndSendScreenshot(
    renderer,
    scene,
    camera,
    container,
    width = 800,
    height = 800,
) {
    try {
        console.log("[screenshot] Capturing...");
        const blob = await captureScreenshot(
            renderer,
            scene,
            camera,
            container,
            width,
            height,
        );
        console.log("[screenshot] Captured, size:", blob.size, "bytes");

        const base64 = await blobToBase64(blob);
        sendScreenshotToLivewire(base64);
    } catch (error) {
        console.error("[screenshot] Failed to capture and send:", error);
    }
}

/**
 * Capture and send screenshot with automatic retry on WebGL/canvas failures.
 *
 * The WebGL renderer may not be stable immediately after model load (GPU not ready,
 * canvas context lost, async texture streaming). This function retries with
 * increasing delays to handle these transient failures.
 *
 * @param {THREE.WebGLRenderer} renderer - The Three.js renderer
 * @param {THREE.Scene} scene - The Three.js scene
 * @param {THREE.Camera} camera - The Three.js camera
 * @param {HTMLElement} container - The container element
 * @param {number} width - Screenshot width (default: 800)
 * @param {number} height - Screenshot height (default: 800)
 * @param {number} maxAttempts - Maximum number of attempts (default: 3)
 * @param {number} baseDelayMs - Base delay between retries in ms (default: 1500)
 */
export async function captureAndSendScreenshotWithRetry(
    renderer,
    scene,
    camera,
    container,
    width = 800,
    height = 800,
    maxAttempts = 3,
    baseDelayMs = 1500,
) {
    for (let attempt = 1; attempt <= maxAttempts; attempt++) {
        try {
            console.log(`[screenshot] Attempt ${attempt}/${maxAttempts}...`);

            const blob = await captureScreenshot(
                renderer,
                scene,
                camera,
                container,
                width,
                height,
            );

            if (!blob || blob.size === 0) {
                throw new Error("Empty blob returned from canvas");
            }

            console.log(
                `[screenshot] Captured on attempt ${attempt}, size:`,
                blob.size,
                "bytes",
            );

            const base64 = await blobToBase64(blob);
            sendScreenshotToLivewire(base64);
            return;
        } catch (error) {
            console.warn(
                `[screenshot] Attempt ${attempt}/${maxAttempts} failed:`,
                error.message,
            );

            if (attempt < maxAttempts) {
                const delay = baseDelayMs * attempt;
                console.log(`[screenshot] Retrying in ${delay}ms...`);
                await new Promise((resolve) => setTimeout(resolve, delay));
            } else {
                console.error(
                    `[screenshot] All ${maxAttempts} attempts failed. Preview unavailable.`,
                );
            }
        }
    }
}
