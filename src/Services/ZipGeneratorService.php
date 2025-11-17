<?php

namespace Tolery\AiCad\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Tolery\AiCad\Models\Chat;
use ZipArchive;

class ZipGeneratorService
{
    /**
     * Generate a ZIP file containing all available CAD files for a chat
     *
     * @return array{success: bool, path: ?string, filename: ?string, files: array, error: ?string}
     */
    public function generateChatFilesZip(Chat $chat): array
    {
        Log::info('[ZIP GENERATOR] Starting ZIP generation', [
            'chat_id' => $chat->id,
            'chat_name' => $chat->name,
        ]);

        // Get the latest message with CAD files
        $message = $chat->messages()
            ->whereNotNull('ai_step_path')
            ->latest()
            ->first();

        if (! $message) {
            Log::error('[ZIP GENERATOR] No message with CAD files found', ['chat_id' => $chat->id]);

            return [
                'success' => false,
                'path' => null,
                'filename' => null,
                'files' => [],
                'error' => 'Aucun fichier CAO disponible pour ce chat.',
            ];
        }

        Log::info('[ZIP GENERATOR] Found message with files', [
            'message_id' => $message->id,
            'ai_step_path' => $message->ai_step_path,
            'ai_cad_path' => $message->ai_cad_path,
            'ai_technical_drawing_path' => $message->ai_technical_drawing_path,
            'ai_screenshot_path' => $message->ai_screenshot_path,
        ]);

        // Generate ZIP file name
        $chatName = $chat->name ?? 'fichier-cao';
        $zipFileName = str($chatName)->slug().'-'.now()->format('YmdHis').'.zip';

        // Create temporary ZIP file
        $tempZipPath = storage_path('app/temp/'.$zipFileName);

        // Ensure temp directory exists
        if (! is_dir(dirname($tempZipPath))) {
            mkdir(dirname($tempZipPath), 0755, true);
            Log::info('[ZIP GENERATOR] Created temp directory', ['dir' => dirname($tempZipPath)]);
        }

        $zip = new ZipArchive;

        if ($zip->open($tempZipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            Log::error('[ZIP GENERATOR] Failed to create ZIP archive', ['temp_path' => $tempZipPath]);

            return [
                'success' => false,
                'path' => null,
                'filename' => null,
                'files' => [],
                'error' => 'Impossible de créer l\'archive ZIP.',
            ];
        }

        $filesAdded = [];

        // Add STEP file
        if ($stepUrl = $message->getStepUrl()) {
            $this->addFileToZip($zip, $stepUrl, 'STEP', $filesAdded);
        }

        // Add OBJ file
        if ($objUrl = $message->getObjUrl()) {
            $this->addFileToZip($zip, $objUrl, 'OBJ', $filesAdded);
        }

        // Add Technical Drawing (PDF)
        if ($technicalDrawingUrl = $message->getTechnicalDrawingUrl()) {
            $this->addFileToZip($zip, $technicalDrawingUrl, 'PDF', $filesAdded);
        }

        // Add Screenshot (PNG)
        if ($screenshotUrl = $message->getScreenshotUrl()) {
            $this->addFileToZip($zip, $screenshotUrl, 'Screenshot', $filesAdded);
        }

        $zip->close();

        if (empty($filesAdded)) {
            // Clean up empty ZIP
            if (file_exists($tempZipPath)) {
                unlink($tempZipPath);
            }
            Log::error('[ZIP GENERATOR] No files added to ZIP');

            return [
                'success' => false,
                'path' => null,
                'filename' => null,
                'files' => [],
                'error' => 'Aucun fichier disponible pour ce chat.',
            ];
        }

        Log::info('[ZIP GENERATOR] ZIP created successfully', [
            'filename' => $zipFileName,
            'path' => $tempZipPath,
            'files_added' => $filesAdded,
            'zip_size' => filesize($tempZipPath),
        ]);

        return [
            'success' => true,
            'path' => $tempZipPath,
            'filename' => $zipFileName,
            'files' => $filesAdded,
            'error' => null,
        ];
    }

    /**
     * Add a file to the ZIP archive
     */
    private function addFileToZip(ZipArchive $zip, string $path, string $type, array &$filesAdded): void
    {
        Log::info("[ZIP GENERATOR] Processing {$type} file", [
            'original_path' => $path,
            'is_url' => filter_var($path, FILTER_VALIDATE_URL),
        ]);

        // If it's a URL (S3), download the content temporarily
        if (filter_var($path, FILTER_VALIDATE_URL)) {
            $this->addRemoteFileToZip($zip, $path, $type, $filesAdded);

            return;
        }

        // Handle local files
        $resolvedPath = $this->resolveFilePath($path);

        Log::info('[ZIP GENERATOR] Resolved local file', [
            'resolved_path' => $resolvedPath,
            'exists' => $resolvedPath ? file_exists($resolvedPath) : false,
        ]);

        if ($resolvedPath && file_exists($resolvedPath)) {
            $zip->addFile($resolvedPath, basename($resolvedPath));
            $filesAdded[] = $type.': '.basename($resolvedPath);
            Log::info("[ZIP GENERATOR] Added {$type} file to ZIP", ['filename' => basename($resolvedPath)]);
        } else {
            Log::warning("[ZIP GENERATOR] {$type} file not found or not accessible", ['path' => $path]);
        }
    }

    /**
     * Add a remote file (S3/URL) to the ZIP archive
     */
    private function addRemoteFileToZip(ZipArchive $zip, string $url, string $type, array &$filesAdded): void
    {
        try {
            Log::info("[ZIP GENERATOR] Downloading remote {$type} file", ['url' => $url]);

            // Download file content
            $content = file_get_contents($url);

            if ($content === false) {
                Log::warning("[ZIP GENERATOR] Failed to download {$type} file", ['url' => $url]);

                return;
            }

            // Extract filename from URL
            $filename = basename(parse_url($url, PHP_URL_PATH));

            // Add content directly to ZIP (no temp file needed)
            $zip->addFromString($filename, $content);
            $filesAdded[] = $type.': '.$filename;

            Log::info("[ZIP GENERATOR] Added remote {$type} file to ZIP", [
                'filename' => $filename,
                'size' => strlen($content),
            ]);
        } catch (\Exception $e) {
            Log::error("[ZIP GENERATOR] Error downloading {$type} file", [
                'url' => $url,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Resolve file path from storage path or URL
     */
    private function resolveFilePath(string $path): ?string
    {
        // URLs are now handled by addRemoteFileToZip()
        if (filter_var($path, FILTER_VALIDATE_URL)) {
            Log::debug('[ZIP GENERATOR] Path is URL, should be handled by addRemoteFileToZip', ['path' => $path]);

            return null;
        }

        // If it's already an absolute path and exists
        if (file_exists($path)) {
            Log::debug('[ZIP GENERATOR] Path exists as absolute', ['path' => $path]);

            return $path;
        }

        // Try to resolve from storage
        $storagePath = storage_path('app/'.$path);
        if (file_exists($storagePath)) {
            Log::debug('[ZIP GENERATOR] Path found in storage/app', ['path' => $storagePath]);

            return $storagePath;
        }

        // Try public storage
        $publicPath = Storage::disk('public')->path($path);
        if (file_exists($publicPath)) {
            Log::debug('[ZIP GENERATOR] Path found in public storage', ['path' => $publicPath]);

            return $publicPath;
        }

        Log::warning('[ZIP GENERATOR] Path not resolved', [
            'original' => $path,
            'tried_storage' => $storagePath,
            'tried_public' => $publicPath,
        ]);

        return null;
    }
}
