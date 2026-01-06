<?php

namespace Tolery\AiCad\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Tolery\AiCad\Models\Chat;
use Tolery\AiCad\Models\ChatMessage;
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

        // Generate ZIP file name: [team_name]_YYYYMMDD_HHMMSS_tolerycad.zip
        $teamName = $chat->team->name ?? 'team';
        $zipFileName = str($teamName)->slug().'_'.now()->format('Ymd_His').'_tolerycad.zip';

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

        // Add STEP file (use raw path, not URL)
        if ($message->ai_step_path) {
            $this->addStorageFileToZip($zip, $message->ai_step_path, 'STEP', $filesAdded);
        }

        // Add Technical Drawing (PDF) (use raw path, not URL)
        if ($message->ai_technical_drawing_path) {
            $this->addStorageFileToZip($zip, $message->ai_technical_drawing_path, 'PDF', $filesAdded);
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
     * Generate a ZIP file for a specific message (version)
     *
     * @return array{success: bool, path: ?string, filename: ?string, files: array, error: ?string}
     */
    public function generateMessageFilesZip(ChatMessage $message): array
    {
        Log::info('[ZIP GENERATOR] Starting ZIP generation for specific message', [
            'message_id' => $message->id,
            'chat_id' => $message->chat_id,
            'version' => $message->getVersionLabel(),
        ]);

        // Check if message has any CAD files
        if (! $message->ai_step_path && ! $message->ai_cad_path) {
            Log::error('[ZIP GENERATOR] No CAD files found for message', ['message_id' => $message->id]);

            return [
                'success' => false,
                'path' => null,
                'filename' => null,
                'files' => [],
                'error' => 'Aucun fichier CAO disponible pour cette version.',
            ];
        }

        // Generate ZIP file name with version: [team_name]_[version]_YYYYMMDD_HHMMSS_tolerycad.zip
        $chat = $message->chat;
        $teamName = $chat->team->name ?? 'team';
        $version = $message->getVersionLabel() ?? 'export';
        $zipFileName = str($teamName)->slug().'_'.$version.'_'.now()->format('Ymd_His').'_tolerycad.zip';

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

        // Add STEP file (use raw path, not URL)
        if ($message->ai_step_path) {
            $this->addStorageFileToZip($zip, $message->ai_step_path, 'STEP', $filesAdded);
        }

        // Add Technical Drawing (PDF) (use raw path, not URL)
        if ($message->ai_technical_drawing_path) {
            $this->addStorageFileToZip($zip, $message->ai_technical_drawing_path, 'PDF', $filesAdded);
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
                'error' => 'Aucun fichier disponible pour cette version.',
            ];
        }

        Log::info('[ZIP GENERATOR] ZIP created successfully for message', [
            'message_id' => $message->id,
            'version' => $version,
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
     * Add a file from Storage to the ZIP archive.
     * This method reads directly from Storage instead of using URLs,
     * avoiding SSL issues with local .test domains.
     */
    private function addStorageFileToZip(ZipArchive $zip, string $storagePath, string $type, array &$filesAdded): void
    {
        Log::info("[ZIP GENERATOR] Processing {$type} file from storage", [
            'storage_path' => $storagePath,
        ]);

        try {
            // Check if the file exists in storage
            if (! Storage::exists($storagePath)) {
                Log::warning("[ZIP GENERATOR] {$type} file not found in storage", ['path' => $storagePath]);

                return;
            }

            // Read file content directly from Storage
            $content = Storage::get($storagePath);

            if ($content === null) {
                Log::warning("[ZIP GENERATOR] Failed to read {$type} file from storage", ['path' => $storagePath]);

                return;
            }

            // Get filename from path
            $filename = basename($storagePath);

            // Add content directly to ZIP
            $zip->addFromString($filename, $content);
            $filesAdded[] = $type.': '.$filename;

            Log::info("[ZIP GENERATOR] Added {$type} file to ZIP from storage", [
                'filename' => $filename,
                'size' => strlen($content),
            ]);
        } catch (\Exception $e) {
            Log::error("[ZIP GENERATOR] Error reading {$type} file from storage", [
                'path' => $storagePath,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
