<?php

namespace Tolery\AiCad\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Tolery\AiCad\Models\PredefinedPromptCache;

class PredefinedPromptCacheService
{
    /**
     * Normalize message for consistent hashing
     */
    public function normalizeMessage(string $message): string
    {
        // Remove extra spaces, lowercase, trim
        return trim(preg_replace('/\s+/', ' ', strtolower($message)));
    }

    /**
     * Generate hash from normalized message
     */
    public function generateHash(string $message): string
    {
        return md5($this->normalizeMessage($message));
    }

    /**
     * Check if message is cached
     */
    public function isCached(string $message): bool
    {
        $hash = $this->generateHash($message);

        return PredefinedPromptCache::where('prompt_hash', $hash)->exists();
    }

    /**
     * Get cached prompt by message
     */
    public function getCached(string $message): ?PredefinedPromptCache
    {
        $hash = $this->generateHash($message);

        return PredefinedPromptCache::where('prompt_hash', $hash)->first();
    }

    /**
     * Store a new cached prompt
     *
     * @param  array<string, string|null>  $files  Array with keys: obj, step, json, technical_drawing, screenshot
     */
    public function store(
        string $message,
        array $files,
        string $chatResponse,
        ?array $simulatedSteps = null
    ): PredefinedPromptCache {
        $hash = $this->generateHash($message);
        $storagePath = $this->getStoragePath($hash);

        Log::info('[CACHE] Storing predefined prompt cache', [
            'hash' => $hash,
            'storage_path' => $storagePath,
            'message' => substr($message, 0, 100),
        ]);

        // Default simulated steps if not provided
        if ($simulatedSteps === null) {
            $simulatedSteps = $this->getDefaultSimulatedSteps();
        }

        return PredefinedPromptCache::updateOrCreate(
            ['prompt_hash' => $hash],
            [
                'prompt_text' => $message,
                'obj_cache_path' => $files['obj'] ?? null,
                'step_cache_path' => $files['step'] ?? null,
                'json_cache_path' => $files['json'] ?? null,
                'technical_drawing_cache_path' => $files['technical_drawing'] ?? null,
                'screenshot_cache_path' => $files['screenshot'] ?? null,
                'chat_response' => $chatResponse,
                'simulated_steps' => $simulatedSteps,
                'generated_at' => now(),
            ]
        );
    }

    /**
     * Increment hits counter for analytics
     */
    public function incrementHits(PredefinedPromptCache $cache): void
    {
        $cache->incrementHits();

        Log::info('[CACHE] Cache hit', [
            'hash' => $cache->prompt_hash,
            'total_hits' => $cache->hits_count,
        ]);
    }

    /**
     * Get storage path for cache files
     */
    public function getStoragePath(string $hash): string
    {
        return "ai-cad-cache/{$hash}";
    }

    /**
     * Get default simulated steps for animation
     */
    protected function getDefaultSimulatedSteps(): array
    {
        return [
            'analysis' => [
                'Analyse des dimensions de la pièce...',
                'Vérification des contraintes de fabrication...',
                'Validation de la géométrie...',
            ],
            'parameters' => [
                'Calcul des paramètres de génération...',
                'Optimisation de la géométrie...',
                'Définition des tolérances...',
            ],
            'generation_code' => [
                'Génération du code CAO...',
                'Construction de la géométrie 3D...',
                'Application des opérations...',
            ],
            'export' => [
                'Export des fichiers STEP et OBJ...',
                'Génération de la mise en plan...',
                'Création du rendu 3D...',
            ],
            'complete' => [
                'Finalisation des exports...',
                'Vérification de la qualité...',
                'Pièce prête au téléchargement !',
            ],
        ];
    }

    /**
     * Delete old caches based on retention policy
     */
    public function cleanupOldCaches(int $retentionDays = 30): int
    {
        $oldCaches = PredefinedPromptCache::olderThan($retentionDays)->get();
        $deletedCount = 0;

        foreach ($oldCaches as $cache) {
            Log::info('[CACHE] Cleaning up old cache', [
                'hash' => $cache->prompt_hash,
                'age_days' => now()->diffInDays($cache->generated_at),
            ]);

            // Delete files from storage
            $this->deleteFiles($cache);

            // Delete database record
            $cache->delete();
            $deletedCount++;
        }

        Log::info('[CACHE] Cleanup completed', [
            'deleted' => $deletedCount,
            'retention_days' => $retentionDays,
        ]);

        return $deletedCount;
    }

    /**
     * Delete files associated with cache entry
     */
    protected function deleteFiles(PredefinedPromptCache $cache): void
    {
        $paths = $cache->getFilePaths();

        foreach ($paths as $type => $path) {
            if ($path && Storage::exists($path)) {
                Storage::delete($path);
                Log::debug('[CACHE] Deleted file', ['type' => $type, 'path' => $path]);
            }
        }

        // Delete cache directory if empty
        $dir = dirname($cache->obj_cache_path ?? '');
        if ($dir && Storage::exists($dir)) {
            $files = Storage::files($dir);
            if (empty($files)) {
                Storage::deleteDirectory($dir);
                Log::debug('[CACHE] Deleted empty directory', ['dir' => $dir]);
            }
        }
    }

    /**
     * Get cache statistics
     */
    public function getStatistics(): array
    {
        return [
            'total_caches' => PredefinedPromptCache::count(),
            'total_hits' => PredefinedPromptCache::sum('hits_count'),
            'most_popular' => PredefinedPromptCache::mostPopular(5)->get()->map(fn ($cache) => [
                'prompt' => substr($cache->prompt_text, 0, 100),
                'hits' => $cache->hits_count,
                'age_days' => now()->diffInDays($cache->generated_at),
            ])->toArray(),
            'oldest_cache' => PredefinedPromptCache::orderBy('generated_at')->first()?->generated_at?->format('Y-m-d H:i:s'),
            'newest_cache' => PredefinedPromptCache::orderByDesc('generated_at')->first()?->generated_at?->format('Y-m-d H:i:s'),
        ];
    }
}
