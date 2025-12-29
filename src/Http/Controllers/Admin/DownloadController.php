<?php

namespace Tolery\AiCad\Http\Controllers\Admin;

use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Tolery\AiCad\Models\Chat;
use Tolery\AiCad\Services\ZipGeneratorService;

class DownloadController extends Controller
{
    /**
     * Télécharge le ZIP d'une conversation (via URL signée).
     *
     * Cette route est protégée par :
     * - Middleware 'signed' (URL signée avec expiration)
     * - Middleware 'auth' (utilisateur authentifié)
     * - Gate authorization (vérification des permissions)
     */
    public function download(Chat $chat): Response|StreamedResponse
    {
        // Vérifier l'autorisation explicite
        Gate::authorize('downloadFiles', $chat);

        Log::info('[ADMIN] Secure download requested', [
            'chat_id' => $chat->id,
            'user_id' => auth()->id(),
        ]);

        try {
            // Générer le ZIP
            $zipService = app(ZipGeneratorService::class);
            $result = $zipService->generateChatFilesZip($chat);

            if (! $result['success']) {
                Log::error('[ADMIN] ZIP generation failed', [
                    'error' => $result['error'],
                    'chat_id' => $chat->id,
                ]);

                return response('Erreur lors de la génération du ZIP: '.$result['error'], 500);
            }

            Log::info('[ADMIN] ZIP generated successfully', [
                'path' => $result['path'],
                'filename' => $result['filename'],
            ]);

            // Retourner le fichier et le supprimer après l'envoi
            return response()
                ->download($result['path'], $result['filename'])
                ->deleteFileAfterSend(true);

        } catch (\Exception $e) {
            Log::error('[ADMIN] Download failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'chat_id' => $chat->id,
            ]);

            return response('Erreur lors du téléchargement', 500);
        }
    }

    /**
     * Télécharge le ZIP via stockage S3 avec URL temporaire.
     * Utilisé uniquement si S3 est configuré.
     */
    public function downloadFromS3(Chat $chat): Response
    {
        Gate::authorize('downloadFiles', $chat);

        try {
            $disk = Storage::disk(config('ai-cad.storage_disk', 's3'));

            // Vérifier que le driver supporte temporaryUrl
            if (! method_exists($disk->getAdapter(), 'temporaryUrl')) {
                return response('S3 storage not configured', 500);
            }

            $zipService = app(ZipGeneratorService::class);
            $result = $zipService->generateChatFilesZip($chat);

            if (! $result['success']) {
                return response('Erreur lors de la génération du ZIP: '.$result['error'], 500);
            }

            // Stocker sur S3 dans un dossier temporaire
            $privatePath = 'temp-downloads/'.uniqid().'_'.basename($result['path']);
            $disk->put($privatePath, file_get_contents($result['path']));
            @unlink($result['path']);

            // Générer URL temporaire (5 minutes)
            $temporaryUrl = $disk->temporaryUrl($privatePath, now()->addMinutes(5));

            // Programmer le nettoyage du fichier après 10 minutes
            dispatch(function () use ($disk, $privatePath) {
                $disk->delete($privatePath);
            })->delay(now()->addMinutes(10));

            Log::info('[ADMIN] S3 temporary URL generated', [
                'chat_id' => $chat->id,
                'path' => $privatePath,
            ]);

            // Rediriger vers l'URL S3 temporaire
            return redirect($temporaryUrl);

        } catch (\Exception $e) {
            Log::error('[ADMIN] S3 download failed', [
                'error' => $e->getMessage(),
                'chat_id' => $chat->id,
            ]);

            return response('Erreur lors du téléchargement depuis S3', 500);
        }
    }
}
