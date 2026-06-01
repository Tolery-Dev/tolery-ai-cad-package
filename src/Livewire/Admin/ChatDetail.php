<?php

namespace Tolery\AiCad\Livewire\Admin;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\View\View;
use Livewire\Component;
use Tolery\AiCad\Models\Chat;
use Tolery\AiCad\Models\ChatMessage;

class ChatDetail extends Component
{
    public Chat $chat;

    public function mount(Chat $chat): void
    {
        $this->chat = $chat->load([
            'team',
            'user',
            'messages' => fn ($query) => $query
                ->with('user')
                ->orderBy('created_at')
                ->orderBy('id'),
        ]);
    }

    /**
     * Get all assistant message versions that have 3D model data.
     *
     * @return array<int, array{label: string, jsonUrl: string, screenshotUrl: string|null}>
     */
    public function getViewerVersions(): array
    {
        return $this->chat->messages
            ->filter(fn (ChatMessage $m) => $m->role === 'assistant' && $m->ai_json_edge_path)
            ->values()
            ->map(fn (ChatMessage $m, int $i) => [
                'label' => 'v'.($i + 1),
                'jsonUrl' => $m->getJSONEdgeUrl(),
                'screenshotUrl' => $m->getScreenshotUrl(),
            ])
            ->all();
    }

    public function downloadZip(): void
    {
        Log::info('[ADMIN] Generating signed download URL for chat', ['chat_id' => $this->chat->id]);

        // Vérifier l'autorisation
        $this->authorize('downloadFiles', $this->chat);

        // Déterminer si S3 est disponible
        $disk = Storage::disk(config('ai-cad.storage_disk', 's3'));
        $useS3 = method_exists($disk->getAdapter(), 'temporaryUrl');

        // Générer l'URL signée appropriée
        if ($useS3) {
            $downloadUrl = URL::temporarySignedRoute(
                'ai-cad.admin.download.s3',
                now()->addMinutes(5),
                ['chat' => $this->chat->id]
            );
        } else {
            $downloadUrl = URL::temporarySignedRoute(
                'ai-cad.admin.download',
                now()->addMinutes(5),
                ['chat' => $this->chat->id]
            );
        }

        Log::info('[ADMIN] Signed download URL generated', [
            'chat_id' => $this->chat->id,
            'uses_s3' => $useS3,
        ]);

        // Ouvrir l'URL dans un nouvel onglet
        $this->js("window.open('{$downloadUrl}', '_blank');");
    }

    /**
     * Charge les codes d'erreur DFM depuis la base de données.
     * Retourne un mapping {code: message} selon la locale courante.
     *
     * @return array<string, string>
     */
    protected function loadDfmErrorCodes(): array
    {
        $modelClass = config('ai-cad.dfm_error_code_model');

        if (! $modelClass || ! class_exists($modelClass)) {
            return [];
        }

        $locale = app()->getLocale();
        $messageColumn = $locale === 'fr' ? 'message_fr' : 'message_en';

        return $modelClass::query()
            ->pluck($messageColumn, 'code')
            ->filter()
            ->all();
    }

    /**
     * Reproduit `checkDfmErrorCode()` du front (chat-messages.blade.php) côté
     * serveur, pour afficher le message d'erreur traduit au lieu du code brut.
     *
     * Cas 1 : le contenu trimé est exactement un code (ex: "104.1").
     * Cas 2 : un code connu est noyé dans un texte plus long (ex: "104.1\nVeuillez
     * réessayer") — regex avec garde-fou pour ne pas matcher "104.1" dans "1104.10".
     *
     * @param  array<string, string>  $dfmErrorCodes
     * @return array{code: string, message: string}|null
     */
    public static function matchDfmError(?string $text, array $dfmErrorCodes): ?array
    {
        if ($text === null || trim($text) === '') {
            return null;
        }

        // Cas 1 : le contenu est exactement un code
        $trimmed = trim($text);
        if (isset($dfmErrorCodes[$trimmed])) {
            return ['code' => $trimmed, 'message' => $dfmErrorCodes[$trimmed]];
        }

        // Cas 2 : un code est noyé dans un texte plus long
        foreach ($dfmErrorCodes as $code => $message) {
            $pattern = '/(^|[^\d.])'.preg_quote((string) $code, '/').'($|[^\d.])/';
            if (preg_match($pattern, $text) === 1) {
                return ['code' => (string) $code, 'message' => $message];
            }
        }

        return null;
    }

    public function render(): View
    {
        return view('ai-cad::livewire.admin.chat-detail', [
            'dfmErrorCodes' => $this->loadDfmErrorCodes(),
        ]);
    }
}
