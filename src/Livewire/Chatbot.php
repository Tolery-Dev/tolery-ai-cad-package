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
use Tolery\AiCad\Enum\MaterialFamily;
use Tolery\AiCad\Models\Chat;
use Tolery\AiCad\Models\ChatMessage;
use Tolery\AiCad\Models\ChatUser;
use Tolery\AiCad\Models\PredefinedPrompt;
use Tolery\AiCad\Services\AiCadStripe;
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

    public ?string $partName = 'Mon fichier';

    /** Export links pour le panneau de configuration */
    public ?string $stepExportUrl = null;

    public ?string $objExportUrl = null;

    public ?string $technicalDrawingUrl = null;

    public ?string $screenshotUrl = null;

    /** Download management */
    public bool $canDownload = false;

    public ?array $downloadStatus = null;

    public bool $showPurchaseModal = false;

    /** Quota information */
    public ?array $quotaStatus = null;

    /** Si true: l'API garde le contexte -> on n'envoie que le dernier message user + éventuelle action */
    protected bool $serverKeepsContext = true;

    /** Nombre de messages d'historique à envoyer si $serverKeepsContext === false */
    protected int $historyLimit = 16;

    public function mount(): void
    {
        // Si le chat n'existe pas encore (ghost chat), ne rien faire
        // Il sera créé au premier message envoyé
        if (! $this->chat->exists) {
            $this->messages = [];

            return;
        }

        if ($this->chat->name) {
            $this->partName = $this->chat->name;
            $this->dispatch('tolery-chat-name-updated', name: $this->partName);
        }

        // Charge l'historique depuis la DB
        $this->messages = $this->mapDbMessagesToArray();

        $objToDisplay = $this->chat->messages()
            ->whereNotNull('ai_cad_path')
            ->orderByDesc('created_at')
            ->first();

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

        // Load quota status
        /** @var ChatUser $user */
        $user = auth()->user();
        $this->quotaStatus = app(FileAccessService::class)->getQuotaStatus($user->team);
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
        // Charger les prompts depuis la DB (actifs uniquement, triés par sort_order)
        // Avec fallback sur la config si la table est vide
        $predefinedPrompts = PredefinedPrompt::where('active', true)
            ->orderBy('sort_order')
            ->get()
            ->map(fn ($prompt) => [
                'name' => $prompt->name,
                'prompt' => $prompt->prompt_text,
            ])
            ->toArray();

        // Fallback sur la config si aucun prompt en DB
        if (empty($predefinedPrompts)) {
            $predefinedPrompts = config('ai-cad.predefined_prompts', []);
        }

        return view('ai-cad::livewire.chatbot', [
            'predefinedPrompts' => $predefinedPrompts,
        ]);
    }

    #[On('updateMaterialFamily')]
    public function updateMaterialFamily(string $materialFamily): void
    {
        try {
            $validated = MaterialFamily::from($materialFamily);
            $this->chat->material_family = $validated;
            $this->chat->save();

            Flux::toast(
                variant: 'success',
                heading: 'Matériau mis à jour',
                text: "Matériau défini sur {$validated->label()}"
            );
        } catch (\Exception $e) {
            Log::error('[AICAD] Failed to update material family', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    #[On('sendRegenerationRequest')]
    public function sendRegenerationRequest(string $message): void
    {
        $this->message = $message;
        $this->send(app(RateLimiter::class));
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

        // Si le chat n'existe pas encore, le créer MAINTENANT
        if (! $this->chat->exists) {
            // S'assurer que les IDs team et user sont bien présents
            // (Livewire peut perdre les attributs non sauvegardés lors de la désérialisation)
            if (! $this->chat->team_id || ! $this->chat->user_id) {
                /** @var ChatUser $user */
                $user = auth()->user();
                $this->chat->team()->associate($user->team);
                $this->chat->user()->associate($user);
            }

            $this->chat->name = $this->partName;
            $this->chat->save(); // ← Création en DB au premier message

            Log::info('[AICAD] Lazy chat created', [
                'chat_id' => $this->chat->id,
                'user_id' => $this->chat->user_id,
            ]);

            // Mettre à jour la session avec l'ID du chat créé
            session()->put('pending_chat', [
                'team_id' => $this->chat->team_id,
                'user_id' => $this->chat->user_id,
                'chat_id' => $this->chat->id, // Stocker l'ID du chat créé
            ]);

            // Dispatcher un événement pour mettre à jour l'URL côté frontend via History API
            $this->dispatch('chat-created', chatId: $this->chat->id);
        }

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

            // Déterminer si c'est une édition (basé sur has_generated_piece)
            $isEdit = $this->shouldUseEditMode();

            // Persiste + UI (utilisateur)
            $mUser = $this->storeMessage('user', $userText);
            $mUser->load('user'); // Charger la relation user
            $this->messages[] = [
                'role' => 'user',
                'content' => $userText,
                'created_at' => Carbon::parse($mUser->created_at)->toIso8601String(),
                'screenshot_url' => null,
                'user' => $mUser->user,
            ];
            $this->dispatch('tolery-chat-append');

            // Placeholder assistant with typing indicator
            $mAsst = $this->storeMessage('assistant', '[TYPING_INDICATOR]');
            $mAsst->load('user'); // Charger la relation user
            $this->messages[] = [
                'role' => 'assistant',
                'content' => '[TYPING_INDICATOR]',
                'created_at' => Carbon::parse($mAsst->created_at)->toIso8601String(),
                'screenshot_url' => null,
                'user' => $mAsst->user,
            ];
            $this->streamingIndex = array_key_last($this->messages);
            $this->lastRefreshAt = microtime(true);

            // Démarre le stream côté navigateur: ouverture du modal + progression live
            logger()->info('[AICAD] Dispatching stream to frontend', [
                'chat_id' => $this->chat->id,
                'session_id' => $this->chat->session_id,
                'is_edit' => $isEdit,
            ]);

            $this->dispatch('aicad-start-stream', message: $userText, sessionId: (string) $this->chat->session_id, isEdit: $isEdit);

        } finally {
            $this->isProcessing = false;
            optional($lock)->release();
            $this->dispatch('tolery-chat-append');
        }
    }

    /**
     * Détermine si la requête doit être envoyée en mode édition
     * Retourne true si une pièce a déjà été générée avec succès
     */
    protected function shouldUseEditMode(): bool
    {
        // Si le chat a déjà généré une pièce, toujours en mode édition
        if ($this->chat->has_generated_piece) {
            return true;
        }

        // Sinon, première génération
        return false;
    }

    // Note: Les handlers chatObjectClick et chatObjectClickReal ont été remplacés
    // par le FaceSelectionManager côté JavaScript qui gère les chips de sélection
    // et l'injection du contexte de face dans le message avant envoi.

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
            $oldSessionId = $this->chat->session_id;
            $this->chat->session_id = $final['session_id'];
            $this->chat->save();
            $this->chat->refresh(); // Rafraîchit le modèle depuis la DB pour éviter la staleness

            logger()->info('[AICAD] Session ID updated in DB', [
                'chat_id' => $this->chat->id,
                'old_session_id' => $oldSessionId,
                'new_session_id' => $this->chat->session_id,
            ]);
        } else {
            logger()->info('[AICAD] Session ID unchanged', [
                'chat_id' => $this->chat->id,
                'session_id' => $this->chat->session_id,
                'received_session_id' => $final['session_id'] ?? null,
            ]);
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

        // Détecter si c'est une génération réussie (présence de fichiers exportés)
        $isSuccessfulGeneration =
            ! empty($objUrl) ||
            ! empty($stepUrl) ||
            ! empty($tessUrl);

        // Si génération réussie et pas encore marquée, marquer maintenant
        if ($isSuccessfulGeneration && ! $this->chat->has_generated_piece) {
            $this->chat->has_generated_piece = true;
            $this->chat->save();

            Log::info('[AICAD] First piece generated successfully', [
                'chat_id' => $this->chat->id,
                'session_id' => $final['session_id'] ?? null,
            ]);
        }

        // Rafraîchit les messages depuis la DB pour mettre à jour la vue (et supprimer le typing indicator)
        $this->messages = $this->mapDbMessagesToArray();

        // Déclenche le rafraîchissement UI (scroll + viewer) puis chargement des assets
        $this->dispatch('tolery-chat-append');
        $this->dispatchViewerEvents($asst);
    }

    /**
     * Récupère le dernier message assistant inséré (placeholder avec typing indicator).
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
        return $this->chat->messages()->with('user')->orderBy('created_at')->get()
            ->map(fn (ChatMessage $m) => [
                'id' => $m->id,
                'role' => $m->role,
                'content' => (string) $m->message,
                'created_at' => Carbon::parse($m->created_at)->toIso8601String(),
                'screenshot_url' => $m->getScreenshotUrl(),
                'version' => $m->getVersionLabel(), // "v1", "v2", "v3" ou null
                'user' => $m->user, // Pour afficher l'avatar
            ])->all();
    }

    protected function storeMessage(string $role, string $content): ChatMessage
    {
        return $this->chat->messages()->create([
            'role' => $role,
            'message' => $content,
            'user_id' => auth()->id(),
        ]);
    }

    protected function appendAssistant(string $text): void
    {
        $m = $this->storeMessage('assistant', $text);
        $m->load('user'); // Charger la relation user
        $this->messages[] = [
            'role' => 'assistant',
            'content' => $text,
            'created_at' => Carbon::parse($m->created_at)->toIso8601String(),
            'screenshot_url' => null,
            'user' => $m->user,
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
            $this->quotaStatus = null;

            return;
        }

        $fileAccessService = app(FileAccessService::class);
        $status = $fileAccessService->canDownloadChat($team, $this->chat);

        $this->canDownload = $status['can_download'];
        $this->downloadStatus = $status;

        // Met à jour le quota affiché dans le header
        $this->quotaStatus = $fileAccessService->getQuotaStatus($team);
    }

    /**
     * Initie le téléchargement du fichier CAO
     * Gère la logique d'achat/abonnement si nécessaire
     */
    public function initiateDownload(): void
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

        // Stocker le ZIP dans un emplacement accessible et créer une URL de téléchargement
        $publicPath = 'downloads/'.basename($result['path']);
        Storage::disk('public')->put($publicPath, file_get_contents($result['path']));

        // Supprimer le fichier temporaire
        @unlink($result['path']);

        // Déclenche le téléchargement via JavaScript
        $downloadUrl = Storage::disk('public')->url($publicPath);
        $filename = $result['filename'];

        logger()->info('[CHATBOT] Triggering download', [
            'url' => $downloadUrl,
            'filename' => $filename,
        ]);

        // Utiliser $this->js() pour déclencher directement le téléchargement
        $this->js("
            (function() {
                const link = document.createElement('a');
                link.href = '{$downloadUrl}';
                link.download = '{$filename}';
                link.style.display = 'none';
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
            })();
        ");

        Flux::toast(
            variant: 'success',
            heading: 'Téléchargement lancé',
            text: 'Votre archive contenant tous les fichiers CAO est en cours de téléchargement.'
        );
    }

    /**
     * Redirige vers la page de souscription d'abonnement
     */
    public function redirectToSubscription(): void
    {
        $this->showPurchaseModal = false;

        // Rediriger vers la page de gestion d'abonnement ToleryCad
        $this->redirect(route('client.tolerycad.subscription'));
    }

    /**
     * Initie l'achat d'un fichier unique via Stripe
     */
    public function purchaseFile(): void
    {
        /** @var ChatUser $user */
        $user = auth()->user();
        $team = $user->team;

        if (! $team) {
            Flux::toast(
                variant: 'danger',
                heading: 'Erreur',
                text: 'Aucune équipe associée à votre compte.'
            );

            return;
        }

        $fileAccessService = app(FileAccessService::class);
        $aiCadStripe = app(AiCadStripe::class);

        // Récupère le prix de l'achat unitaire
        $amount = $fileAccessService->getOneTimePurchasePrice();

        // Récupère le dernier message assistant pour le screenshot
        $lastAssistant = $this->findLatestAssistantMessage();
        $screenshotUrl = $lastAssistant?->getScreenshotUrl();

        try {
            // Crée un PaymentIntent Stripe avec les métadonnées nécessaires
            $paymentIntent = $aiCadStripe->createPaymentIntent(
                $amount,
                'eur',
                [
                    'team_id' => (string) $team->id,
                    'chat_id' => (string) $this->chat->id,
                    'type' => 'file_purchase',
                ]
            );

            Log::info('[AICAD] PaymentIntent created for file purchase', [
                'payment_intent_id' => $paymentIntent->id,
                'team_id' => $team->id,
                'chat_id' => $this->chat->id,
                'amount' => $amount,
            ]);

            // Ferme le modal d'achat/abonnement
            $this->showPurchaseModal = false;

            // Ouvre le modal de paiement Stripe avec le client_secret
            $this->dispatch('show-stripe-payment-modal',
                clientSecret: $paymentIntent->client_secret,
                amount: $amount,
                chatId: $this->chat->id,
                screenshotUrl: $screenshotUrl
            );

        } catch (\Exception $e) {
            Log::error('[AICAD] Failed to create PaymentIntent', [
                'error' => $e->getMessage(),
                'team_id' => $team->id,
                'chat_id' => $this->chat->id,
            ]);

            Flux::toast(
                variant: 'danger',
                heading: 'Erreur',
                text: 'Impossible d\'initialiser le paiement. Veuillez réessayer.'
            );
        }
    }

    #[On('payment-completed')]
    public function handlePaymentCompleted(): void
    {
        // Rafraîchir le statut de téléchargement après un paiement réussi
        $this->updateDownloadStatus();
        $this->refreshFromDb();

        Log::info('[AICAD] Download status refreshed after payment', [
            'chat_id' => $this->chat->id,
            'can_download' => $this->canDownload,
        ]);
    }

    /* ----------------- Version Helpers ----------------- */

    /**
     * Get the version label of the current (latest) version.
     */
    public function getCurrentVersionLabel(): ?string
    {
        $lastAssistant = $this->chat->messages()
            ->where('role', ChatMessage::ROLE_ASSISTANT)
            ->whereNotNull('ai_cad_path')
            ->orderByDesc('created_at')
            ->first();

        return $lastAssistant?->getVersionLabel();
    }

    /**
     * Get all available versions with their metadata.
     *
     * @return array<int, array{id: int, label: string, date: string}>
     */
    public function getAvailableVersions(): array
    {
        return $this->chat->messages()
            ->where('role', ChatMessage::ROLE_ASSISTANT)
            ->whereNotNull('ai_cad_path')
            ->orderBy('created_at', 'asc')
            ->get()
            ->map(fn (ChatMessage $msg) => [
                'id' => $msg->id,
                'label' => $msg->getVersionLabel(),
                'date' => $msg->created_at->format('d/m/Y H:i'),
            ])
            ->toArray();
    }

    /**
     * Download files from a specific version (message).
     */
    public function downloadVersion(int $messageId): void
    {
        logger()->info('[CHATBOT] downloadVersion called', [
            'message_id' => $messageId,
            'chat_id' => $this->chat->id,
        ]);

        // Find the message and verify it belongs to this chat
        $message = ChatMessage::where('chat_id', $this->chat->id)
            ->where('id', $messageId)
            ->where('role', ChatMessage::ROLE_ASSISTANT)
            ->whereNotNull('ai_cad_path')
            ->first();

        if (! $message) {
            logger()->error('[CHATBOT] Message not found or invalid', ['message_id' => $messageId]);
            Flux::toast(
                variant: 'danger',
                heading: 'Erreur',
                text: 'Version introuvable.'
            );

            return;
        }

        // Check download permission (same as initiateDownload)
        /** @var ChatUser $user */
        $user = auth()->user();
        $team = $user->team;

        if (! $team) {
            logger()->error('[CHATBOT] No team found for version download', ['user_id' => $user->id]);
            Flux::toast(
                variant: 'danger',
                heading: 'Erreur',
                text: 'Impossible de télécharger : aucune équipe associée.'
            );

            return;
        }

        logger()->info('[CHATBOT] Team found for version download', ['team_id' => $team->id]);

        $fileAccessService = app(FileAccessService::class);
        $status = $fileAccessService->canDownloadMessage($team, $this->chat, $message);

        logger()->info('[CHATBOT] Download permission checked for version', [
            'can_download' => $status['can_download'],
            'status' => $status,
            'version' => $message->getVersionLabel(),
        ]);

        if (! $status['can_download']) {
            logger()->warning('[CHATBOT] Download not allowed for version, showing purchase modal');
            // Affiche le modal d'achat/abonnement avec les options
            $this->showPurchaseModal = true;
            $this->downloadStatus = $status;

            return;
        }

        // Enregistre le téléchargement de cette version spécifique (décompte 1 quota si jamais téléchargée)
        // Chaque version téléchargée consomme 1 quota. Re-télécharger la même version ne coûte rien.
        $fileAccessService->recordMessageDownload($team, $this->chat, $message);
        logger()->info('[CHATBOT] Version download recorded', ['version' => $message->getVersionLabel()]);

        // Met à jour le statut
        $this->updateDownloadStatus();

        // Generate ZIP for this specific message
        logger()->info('[CHATBOT] Generating ZIP for version', ['version' => $message->getVersionLabel()]);
        $zipService = app(ZipGeneratorService::class);
        $result = $zipService->generateMessageFilesZip($message);

        if (! $result['success']) {
            logger()->error('[CHATBOT] ZIP generation failed', ['error' => $result['error']]);
            Flux::toast(
                variant: 'danger',
                heading: 'Erreur',
                text: $result['error']
            );

            return;
        }

        logger()->info('[CHATBOT] ZIP generated successfully for version', [
            'version' => $message->getVersionLabel(),
            'filename' => $result['filename'],
            'files' => $result['files'],
        ]);

        // Store ZIP in accessible location and create download URL
        $publicPath = 'downloads/'.basename($result['path']);
        Storage::disk('public')->put($publicPath, file_get_contents($result['path']));

        // Delete temporary file
        @unlink($result['path']);

        // Trigger download via JavaScript
        $downloadUrl = Storage::disk('public')->url($publicPath);
        $filename = $result['filename'];

        logger()->info('[CHATBOT] Triggering version download', [
            'url' => $downloadUrl,
            'filename' => $filename,
            'version' => $message->getVersionLabel(),
        ]);

        // Use $this->js() to trigger download directly
        $this->js("
            (function() {
                const link = document.createElement('a');
                link.href = '{$downloadUrl}';
                link.download = '{$filename}';
                link.style.display = 'none';
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
            })();
        ");

        Flux::toast(
            variant: 'success',
            heading: 'Téléchargement lancé',
            text: "Version {$message->getVersionLabel()} en cours de téléchargement."
        );
    }

    /**
     * Notifie l'équipe Tolery d'un échec de génération après plusieurs tentatives.
     * Appelé depuis le frontend quand le retry max est atteint.
     */
    public function notifyStreamFailure(array $data): void
    {
        /** @var ChatUser $user */
        $user = auth()->user();

        $errorDetails = [
            'chat_id' => $this->chat->id,
            'chat_session_id' => $this->chat->session_id,
            'user_id' => $user->id,
            'user_email' => $user->email,
            'team_id' => $user->team?->id,
            'team_name' => $user->team?->name,
            'message_preview' => $data['message'] ?? 'N/A',
            'error_type' => $data['errorType'] ?? 'unknown',
            'error_message' => $data['errorMessage'] ?? 'N/A',
            'retry_count' => $data['retryCount'] ?? 0,
            'timestamp' => now()->toIso8601String(),
            'environment' => app()->environment(),
        ];

        // Log détaillé pour investigation
        Log::error('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        Log::error('[AICAD] ❌ STREAM FAILURE - TEAM NOTIFICATION');
        Log::error('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        Log::error('[AICAD] User: '.$user->email.' (ID: '.$user->id.')');
        Log::error('[AICAD] Chat ID: '.$this->chat->id);
        Log::error('[AICAD] Session ID: '.($this->chat->session_id ?? 'N/A'));
        Log::error('[AICAD] Error Type: '.($data['errorType'] ?? 'unknown'));
        Log::error('[AICAD] Error Message: '.($data['errorMessage'] ?? 'N/A'));
        Log::error('[AICAD] Retry Count: '.($data['retryCount'] ?? 0));
        Log::error('[AICAD] Message Preview: '.($data['message'] ?? 'N/A'));
        Log::error('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');

        // Log channel spécifique pour alerting (Slack, etc.)
        // Utiliser try-catch au cas où le channel n'est pas configuré
        try {
            Log::channel('slack')->critical('[AICAD] Échec de génération CAO après '.$errorDetails['retry_count'].' tentatives', $errorDetails);
        } catch (\Exception $e) {
            // Fallback: log to default channel if slack is not configured
            Log::critical('[AICAD] Échec de génération CAO après '.$errorDetails['retry_count'].' tentatives (Slack unavailable)', $errorDetails);
        }

        // Store an assistant message indicating the failure
        $failureMessage = 'Une erreur technique est survenue lors de la génération de votre pièce. '
            .'L\'équipe Tolery a été automatiquement notifiée et travaille à résoudre ce problème. '
            .'Nous vous tiendrons informé dès que possible.';

        $asst = $this->findLatestAssistantMessage();
        if ($asst && $asst->message === '[TYPING_INDICATOR]') {
            $asst->message = $failureMessage;
            $asst->save();
        }

        // Refresh messages to update UI
        $this->messages = $this->mapDbMessagesToArray();
        $this->dispatch('tolery-chat-append');
    }
}
