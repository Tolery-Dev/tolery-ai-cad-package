<?php

namespace Tolery\AiCad\Livewire;

use Flux\Flux;
use Illuminate\Cache\RateLimiter;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Livewire\Attributes\On;
use Livewire\Component;
use Tolery\AiCad\Models\Chat;
use Tolery\AiCad\Models\ChatMessage;
use Tolery\AiCad\Models\ChatUser;
use Tolery\AiCad\Services\FileAccessService;
use Tolery\AiCad\Services\ZipGeneratorService;

class Chatbot extends Component
{
    /** Injecté par <livewire:chatbot :$chat /> */
    public Chat $chat;

    /** Données pour la vue */
    public array $messages = [];      // [['role'=>'user|assistant','content'=>'...', 'created_at'=>iso], ...]

    public string $message = '';

    public bool $isProcessing = false;

    /** Streaming */
    protected ?int $streamingIndex = null; // index du message assistant courant

    protected float $lastRefreshAt = 0.0;  // throttle des re-rendus

    /** Réglages */
    protected int $httpTimeoutSec = 380;

    protected int $maxRetries = 1;

    protected int $ratePerMinute = 10;    // anti-spam

    protected int $lockSeconds = 12;    // anti double submit

    public ?string $partName = '';

    /** Export links pour le panneau de configuration */
    public ?string $stepExportUrl = null;

    public ?string $objExportUrl = null;

    public ?string $technicalDrawingUrl = null;

    public ?string $screenshotUrl = null;

    /** Download management */
    public bool $canDownload = false;

    public ?array $downloadStatus = null;

    public bool $showPurchaseModal = false;

    /** Si true: l'API garde le contexte -> on n'envoie que le dernier message user + éventuelle action */
    protected bool $serverKeepsContext = true;

    /** Nombre de messages d'historique à envoyer si $serverKeepsContext === false */
    protected int $historyLimit = 16;

    public function mount(): void
    {
        // Si nouveau chat, on s’assure qu’il existe en base
        if (! $this->chat->exists) {
            $chat = new Chat;
            /** @var ChatUser $user */
            $user = auth()->user();
            $chat->team()->associate($user->team);
            $chat->user()->associate($user);
            $chat->save();
            $chat->name ??= 'Ma nouvelle pièce';
            $chat->save();
            $this->chat = $chat;
        }

        if ($this->chat->name) {
            $this->partName = $this->chat->name;
            $this->dispatch('tolery-chat-name-updated', name: $this->partName);
        }

        // Charge l'historique depuis la DB
        $this->messages = $this->mapDbMessagesToArray();

        $objToDisplay = $this->chat->messages->isEmpty() ?
            null
            :
            $this->chat->messages
                ->whereNotNull('ai_cad_path')
                ->last();

        if ($objToDisplay) {
            // 1. JSON tessellé pour la sélection de faces
            $jsonUrl = $objToDisplay->getJSONEdgeUrl();

            if ($jsonUrl) {
                $this->dispatch('jsonEdgesLoaded', jsonPath: $jsonUrl);
            }

            // 2. Initialise les liens d'export pour le panneau
            $this->updateExportUrls($objToDisplay);

            // 3. Dispatch des liens de téléchargement initiaux
            $this->dispatchExportLinks($objToDisplay);

            // 4. Initialize download status
            $this->updateDownloadStatus();
        }
    }

    public function updatedEdgesShow($value): void
    {
        $this->dispatch('toggleShowEdges', show: $value);
    }

    public function updatedEdgesColor($value): void
    {
        $this->dispatch('updatedEdgeColor', color: $value);
    }

    public function updatedPartName($value): void
    {
        $this->partName = trim($value) === '' ? null : $value;
        if ($this->partName) {
            $this->chat->name = $this->partName;
            $this->chat->save();
            $this->dispatch('tolery-chat-name-updated', name: $this->partName);
        }
    }

    public function render(): View
    {
        return view('ai-cad::livewire.chatbot');
    }

    protected function rules(): array
    {
        return [
            'message' => ['required', 'string', 'min:2', 'max:5000'],
        ];
    }

    public function sendPredefinedPrompt(string $prompt): void
    {
        // Remplit le champ message avec le prompt prédéfini
        $this->message = $prompt;
        // Appelle la méthode send normale
        $this->send(app(RateLimiter::class));
    }

    #[On('open-file-upload')]
    public function openFileUpload(string $type): void
    {
        // TODO: Implement file upload modal/dialog
        // For now, just dispatch an event to show a toast
        Flux::toast(
            variant: 'info',
            heading: 'Bientôt disponible',
            text: 'L\'import de fichiers '.strtoupper($type).' sera disponible prochainement.'
        );
    }

    public function send(RateLimiter $limiter): void
    {
        if ($this->isProcessing) {
            return;
        }

        $this->validate();

        $rateKey = 'aicad:chat:'.($this->chat->id ?: request()->session()->getId());
        if ($limiter->tooManyAttempts($rateKey, $this->ratePerMinute)) {
            $wait = $limiter->availableIn($rateKey);
            $this->appendAssistant("Vous envoyez des messages trop vite. Réessayez dans {$wait}s.");
            $this->dispatch('tolery-chat-append');

            return;
        }

        $limiter->hit($rateKey, 60);

        $lock = Cache::lock("aicad:send:chat:{$this->chat->id}", $this->lockSeconds);
        if (! $lock->get()) {
            return;
        }
        try {
            $this->isProcessing = true;
            $userText = trim($this->message);
            $this->message = '';

            // Déterminer si c'est une édition (au moins un assistant déjà existant avant ce tour)
            $hasAnyAssistant = $this->chat->messages()->where('role', ChatMessage::ROLE_ASSISTANT)->exists();
            $isEdit = $hasAnyAssistant; // false la toute première fois, true ensuite

            // Persiste + UI (utilisateur)
            $mUser = $this->storeMessage('user', $userText);
            $this->messages[] = [
                'role' => 'user',
                'content' => $userText,
                'created_at' => Carbon::parse($mUser->created_at)->toIso8601String(),
                'screenshot_url' => null,
            ];
            $this->dispatch('tolery-chat-append');

            // Placeholder assistant "AI thinking…"
            $mAsst = $this->storeMessage('assistant', 'AI thinking…');
            $this->messages[] = [
                'role' => 'assistant',
                'content' => 'AI thinking…',
                'created_at' => Carbon::parse($mAsst->created_at)->toIso8601String(),
                'screenshot_url' => null,
            ];
            $this->streamingIndex = array_key_last($this->messages);
            $this->lastRefreshAt = microtime(true);

            // Démarre le stream côté navigateur: ouverture du modal + progression live
            $this->dispatch('aicad-start-stream', message: $userText, sessionId: (string) $this->chat->session_id, isEdit: $isEdit);

        } finally {
            $this->isProcessing = false;
            optional($lock)->release();
            $this->dispatch('tolery-chat-append');
        }
    }

    #[On('chatObjectClick')]
    public function handleObjectClick(string|int|null $objectId = null): void
    {
        if ($objectId === null || $objectId === '') {
            return;
        }

        // Préremplit le textarea sans envoyer automatiquement
        $this->message = "Sélection de face {$objectId} — décrivez les modifications souhaitées (ex: perçage Ø10 au centre, chanfrein 1mm, pli à 90°, etc.).";

        // UX: scroll + focus sur l'input côté vue
        $this->dispatch('tolery-chat-append');
        $this->dispatch('tolery-chat-focus-input', faceId: (string) $objectId);
    }

    #[On('chatObjectClickReal')]
    public function handleObjectClickReal(string|int|null $objectId = null): void
    {
        if ($objectId === null || $objectId === '') {
            return;
        }

        // Même logique que ci-dessus, mais on privilégie l'identifiant "réel" issu du fichier
        $this->message = "Sélection de face {$objectId} — décrivez les modifications souhaitées (ex: perçage Ø10 au centre, chanfrein 1mm, pli à 90°, etc.).";

        $this->dispatch('tolery-chat-append');
        $this->dispatch('tolery-chat-focus-input', faceId: (string) $objectId);
    }

    /**
     * Sauvegarde un screenshot généré côté client (navigateur)
     * Appelé automatiquement après le chargement d'un modèle 3D dans le viewer
     */
    #[On('saveClientScreenshot')]
    public function saveClientScreenshot(string $base64Data): void
    {
        try {
            // Décode le base64
            $imageData = base64_decode($base64Data, true);

            if ($imageData === false) {
                Log::warning('[AICAD] Failed to decode base64 screenshot data');

                return;
            }

            // Trouve le dernier message assistant
            $lastAssistant = $this->findLatestAssistantMessage();

            if (! $lastAssistant) {
                Log::warning('[AICAD] No assistant message found to attach screenshot');

                return;
            }

            // Génère un nom de fichier unique
            $folder = $this->chat->getStorageFolder();
            $filename = uniqid('screenshot_').'.png';
            $path = "{$folder}/{$filename}";

            // Sauvegarde le fichier
            Storage::put($path, $imageData);

            // Met à jour le message avec le chemin du screenshot
            $lastAssistant->ai_screenshot_path = $path;
            $lastAssistant->save();

            Log::info('[AICAD] Client screenshot saved', [
                'chat_id' => $this->chat->id,
                'message_id' => $lastAssistant->id,
                'path' => $path,
                'size' => strlen($imageData),
            ]);
        } catch (\Exception $e) {
            Log::error('[AICAD] Failed to save client screenshot', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    public function refreshFromDb(): void
    {
        $this->messages = $this->mapDbMessagesToArray();

        $last = $this->chat->messages()->orderByDesc('id')->first();
        if ($last && $last->ai_json_edge_path) {
            $this->dispatch('jsonEdgesLoaded', jsonPath: $last->getJSONEdgeUrl());
        } elseif ($last && $last->ai_cad_path) {
            $this->dispatch('objLoaded', objPath: $last->getObjUrl());
        }

        $this->dispatch('tolery-chat-append');
    }

    /**
     * Persiste la réponse finale du stream côté serveur.
     * Reçoit le JSON final_response complet
     * Call depuis la vue (chatbot.blade.php)
     *
     * @param array{
     *   chat_response?:string,
     *   session_id?:string,
     *   obj_export?:?string,
     *   step_export?:?string,
     *   json_export?:?string,
     *   technical_drawing_export?:?string,
     *   screenshot_export?:?string,
     *   tessellated_export?:?string,
     *   attribute_and_transientid_map?:mixed,
     *   manufacturing_errors?:array
     * } $final
     */
    public function saveStreamFinal(array $final): void
    {
        Log::info('[AICAD] saveStreamFinal', ['final' => $final]);

        $chatResponse = (string) ($final['chat_response'] ?? '');
        $objUrl = $final['obj_export'] ?? null;
        $stepUrl = $final['step_export'] ?? null; // STEP export
        $jsonModelUrl = $final['json_export'] ?? null; // JSON principal pour affichage
        $tessUrl = $final['tessellated_export'] ?? null; // JSON tessellé (héritage)
        $techDrawingUrl = $final['technical_drawing_export'] ?? null; // plan technique
        $screenshotUrl = $final['screenshot_export'] ?? null; // screenshot 800x800px

        // Save session_id
        if (isset($final['session_id']) && $this->chat->session_id !== $final['session_id']) {
            $this->chat->session_id = $final['session_id'];
            $this->chat->save();
        }

        /** @var ChatMessage|null $asst */
        $asst = $this->findLatestAssistantMessage();

        // Détermine le texte final du message (priorité à chat_response, sinon conserve, sinon 'OK')
        $messageText = $chatResponse !== '' ? $chatResponse : (($asst?->message) ?: 'OK');

        if ($asst) {
            $asst->message = $messageText;
        } else {
            // Filet de sécurité: si pas de placeholder, on crée un message assistant
            $asst = $this->storeMessage(ChatMessage::ROLE_ASSISTANT, $messageText);
        }

        // Applique les URLs/exports aux champs du message
        $this->applyFinalAssetsToMessage($asst, $objUrl, $stepUrl, $jsonModelUrl, $tessUrl, $techDrawingUrl, $screenshotUrl);

        // Optionnel: journaliser la réponse complète pour audit/debug
        logger()->info('[AICAD] final_response saved', ['chat_id' => $this->chat->id, 'final' => $final]);

        $asst->save();

        // Déclenche le rafraîchissement UI (scroll + viewer) puis chargement des assets
        $this->dispatch('tolery-chat-append');
        $this->dispatchViewerEvents($asst);
    }

    /**
     * Récupère le dernier message assistant inséré (placeholder "AI thinking…").
     */
    private function findLatestAssistantMessage(): ?ChatMessage
    {
        return $this->chat->messages()
            ->where('role', ChatMessage::ROLE_ASSISTANT)
            ->orderByDesc('id')
            ->first();
    }

    /**
     * Applique les exports finaux (OBJ, STEP, JSON edges, plan technique, screenshot) au message.
     * Préférence: json_export, fallback: tessellated_export.
     */
    private function applyFinalAssetsToMessage(
        ChatMessage $asst,
        mixed $objUrl,
        mixed $stepUrl,
        mixed $jsonModelUrl,
        mixed $tessUrl,
        mixed $techDrawingUrl,
        mixed $screenshotUrl
    ): void {
        // Télécharge et stocke les fichiers localement si ce sont des URLs externes
        if (is_string($objUrl) && $objUrl !== '') {
            $asst->ai_cad_path = $this->downloadAndStoreFile($objUrl, 'obj');
        }

        if (is_string($stepUrl) && $stepUrl !== '') {
            $asst->ai_step_path = $this->downloadAndStoreFile($stepUrl, 'step');
        }

        // Priorité à json_export pour l'affichage 3D JSON (fallback compat sur tessellated_export)
        if (is_string($jsonModelUrl) && $jsonModelUrl !== '') {
            $asst->ai_json_edge_path = $this->downloadAndStoreFile($jsonModelUrl, 'json');
        } elseif (is_string($tessUrl) && $tessUrl !== '') {
            $asst->ai_json_edge_path = $this->downloadAndStoreFile($tessUrl, 'json');
        }

        if (is_string($techDrawingUrl) && $techDrawingUrl !== '') {
            $asst->ai_technical_drawing_path = $this->downloadAndStoreFile($techDrawingUrl, 'pdf');
        }

        if (is_string($screenshotUrl) && $screenshotUrl !== '') {
            $asst->ai_screenshot_path = $this->downloadAndStoreFile($screenshotUrl, 'png');
        }
    }

    /**
     * Télécharge un fichier depuis une URL et le stocke localement
     * Retourne le chemin de stockage ou l'URL si ce n'est pas une URL HTTP
     */
    private function downloadAndStoreFile(string $url, string $extension): string
    {
        // Si ce n'est pas une URL HTTP/HTTPS, on retourne tel quel (peut-être déjà un chemin local)
        if (! filter_var($url, FILTER_VALIDATE_URL) || ! preg_match('/^https?:\/\//i', $url)) {
            return $url;
        }

        try {
            // Prépare les en-têtes HTTP avec Bearer token si configuré
            $apiKey = config('ai-cad.api.key');
            $headers = [];
            if ($apiKey) {
                $headers[] = "Authorization: Bearer {$apiKey}";
            }

            $contextOptions = [
                'http' => [
                    'timeout' => 30,
                    'ignore_errors' => true,
                    'header' => implode("\r\n", $headers),
                ],
            ];

            // Télécharge le contenu
            $content = file_get_contents($url, false, stream_context_create($contextOptions));

            if ($content === false) {
                logger()->warning("[AICAD] Failed to download file from {$url}");

                return $url; // Fallback sur l'URL originale
            }

            // Génère un chemin de stockage dans le dossier du chat
            $folder = $this->chat->getStorageFolder();
            $filename = uniqid('cad_').'.'.$extension;
            $path = "{$folder}/{$filename}";

            // Stocke le fichier
            Storage::put($path, $content);

            logger()->info("[AICAD] Downloaded and stored file: {$url} -> {$path}");

            return $path;
        } catch (\Exception $e) {
            logger()->error("[AICAD] Error downloading file: {$url}", ['error' => $e->getMessage()]);

            return $url; // Fallback sur l'URL originale
        }
    }

    /**
     * Déclenche les événements de viewer selon l'asset disponible (préférence JSON, fallback OBJ).
     */
    private function dispatchViewerEvents(ChatMessage $asst): void
    {
        // Préférence: JSON
        if ($asst->ai_json_edge_path) {
            $this->dispatch('jsonEdgesLoaded', jsonPath: $asst->getJSONEdgeUrl());
        } elseif ($asst->ai_cad_path) {
            // Fallback OBJ
            $this->dispatch('objLoaded', objPath: $asst->getObjUrl());
        }

        // Dispatch des liens de téléchargement vers le panneau Alpine
        $this->dispatchExportLinks($asst);
    }

    /**
     * Met à jour les propriétés publiques d'export pour le panneau
     */
    private function updateExportUrls(ChatMessage $asst): void
    {
        $this->stepExportUrl = $asst->ai_step_path ? $asst->getStepUrl() : null;
        $this->objExportUrl = $asst->ai_cad_path ? $asst->getObjUrl() : null;
        $this->technicalDrawingUrl = $asst->ai_technical_drawing_path ? $asst->getTechnicalDrawingUrl() : null;
        $this->screenshotUrl = $asst->ai_screenshot_path ? $asst->getScreenshotUrl() : null;
    }

    /**
     * Envoie les liens de téléchargement disponibles au panneau de configuration
     */
    private function dispatchExportLinks(ChatMessage $asst): void
    {
        $this->updateExportUrls($asst);

        $exports = [
            'step' => $this->stepExportUrl,
            'obj' => $this->objExportUrl,
            'technical_drawing' => $this->technicalDrawingUrl,
            'screenshot' => $this->screenshotUrl,
        ];

        $this->dispatch('cad-exports-updated', ...$exports);
    }

    /* ----------------- Helpers ----------------- */

    protected function mapDbMessagesToArray(): array
    {
        return $this->chat->messages()->orderBy('created_at')->get()
            ->map(fn (ChatMessage $m) => [
                'role' => $m->role,
                'content' => (string) $m->message,
                'created_at' => Carbon::parse($m->created_at)->toIso8601String(),
                'screenshot_url' => $m->getScreenshotUrl(),
            ])->all();
    }

    protected function storeMessage(string $role, string $content): ChatMessage
    {
        return $this->chat->messages()->create(['role' => $role, 'message' => $content]);
    }

    protected function appendAssistant(string $text): void
    {
        $m = $this->storeMessage('assistant', $text);
        $this->messages[] = [
            'role' => 'assistant',
            'content' => $text,
            'created_at' => Carbon::parse($m->created_at)->toIso8601String(),
            'screenshot_url' => null,
        ];
    }

    protected function appendAssistantDelta(string $delta, ChatMessage $dbMessage): void
    {
        if ($this->streamingIndex === null) {
            return;
        }

        $this->messages[$this->streamingIndex]['content'] .= $delta;
        $dbMessage->message = ($dbMessage->message ?? '').$delta;
        $dbMessage->save();

        $now = microtime(true);
        if (($now - $this->lastRefreshAt) >= 0.10) {
            $this->lastRefreshAt = $now;
            $this->dispatch('tolery-chat-append');
            $this->dispatch('$refresh');
        }
    }

    protected function setAssistantFull(string $text, ChatMessage $dbMessage): void
    {
        if ($this->streamingIndex === null) {
            return;
        }

        $this->messages[$this->streamingIndex]['content'] = $text;
        $dbMessage->message = $text;
        $dbMessage->save();

        $this->dispatch('tolery-chat-append');
        $this->dispatch('$refresh');
    }

    /**
     * Met à jour le statut de téléchargement pour l'utilisateur actuel
     */
    protected function updateDownloadStatus(): void
    {
        /** @var ChatUser $user */
        $user = auth()->user();
        $team = $user->team;

        if (! $team) {
            $this->canDownload = false;
            $this->downloadStatus = null;

            return;
        }

        $fileAccessService = app(FileAccessService::class);
        $status = $fileAccessService->canDownloadChat($team, $this->chat);

        $this->canDownload = $status['can_download'];
        $this->downloadStatus = $status;
    }

    /**
     * Initie le téléchargement du fichier CAO
     * Gère la logique d'achat/abonnement si nécessaire
     */
    public function initiateDownload()
    {
        logger()->info('[CHATBOT] initiateDownload called', ['chat_id' => $this->chat->id]);

        /** @var ChatUser $user */
        $user = auth()->user();
        $team = $user->team;

        if (! $team) {
            logger()->error('[CHATBOT] No team found', ['user_id' => $user->id]);
            Flux::toast(
                variant: 'danger',
                heading: 'Erreur',
                text: 'Impossible de télécharger : aucune équipe associée.'
            );

            return;
        }

        logger()->info('[CHATBOT] Team found', ['team_id' => $team->id]);

        $fileAccessService = app(FileAccessService::class);
        $status = $fileAccessService->canDownloadChat($team, $this->chat);

        logger()->info('[CHATBOT] Download permission checked', [
            'can_download' => $status['can_download'],
            'status' => $status,
        ]);

        if (! $status['can_download']) {
            logger()->warning('[CHATBOT] Download not allowed, showing purchase modal');
            // Affiche le modal d'achat/abonnement
            $this->showPurchaseModal = true;
            $this->downloadStatus = $status;

            return;
        }

        // Enregistre le téléchargement
        $fileAccessService->recordChatDownload($team, $this->chat);
        logger()->info('[CHATBOT] Download recorded');

        // Met à jour le statut
        $this->updateDownloadStatus();

        // Génère le ZIP avec tous les fichiers
        logger()->info('[CHATBOT] Generating ZIP file');
        $zipService = app(ZipGeneratorService::class);
        $result = $zipService->generateChatFilesZip($this->chat);

        if (! $result['success']) {
            logger()->error('[CHATBOT] ZIP generation failed', ['error' => $result['error']]);
            Flux::toast(
                variant: 'danger',
                heading: 'Erreur',
                text: $result['error']
            );

            return;
        }

        logger()->info('[CHATBOT] ZIP generated successfully', [
            'filename' => $result['filename'],
            'files' => $result['files'],
        ]);

        Flux::toast(
            variant: 'success',
            heading: 'Téléchargement lancé',
            text: 'Votre archive contenant tous les fichiers CAO est en cours de téléchargement.'
        );

        logger()->info('[CHATBOT] Returning download response');

        // Retourne le fichier ZIP pour téléchargement
        return response()->download($result['path'], $result['filename'])->deleteFileAfterSend(true);
    }

    #[On('payment-completed')]
    public function handlePaymentCompleted(): void
    {
        // Rafraîchir le statut de téléchargement après un paiement réussi
        $this->refreshFromDb();

        Log::info('[AICAD] Download status refreshed after payment', [
            'chat_id' => $this->chat->id,
        ]);
    }
}
