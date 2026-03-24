<?php

namespace Tolery\AiCad\Livewire;

use Flux\Flux;
use Illuminate\Cache\RateLimiter;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Livewire\Attributes\On;
use Livewire\Component;
use Tolery\AiCad\Enum\MaterialFamily;
use Tolery\AiCad\Jobs\DownloadCadAssetsJob;
use Tolery\AiCad\Models\Chat;
use Tolery\AiCad\Models\ChatMessage;
use Tolery\AiCad\Models\ChatUser;
use Tolery\AiCad\Models\PredefinedPrompt;
use Tolery\AiCad\Models\StepMessage;
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

    public string $composerPlaceholder = 'Décrivez le plus précisément votre pièce ou insérez un lien url ici';

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

    /** Suggestions contextuelles affichées après la dernière réponse du bot */
    public array $contextualSuggestions = [];

    /** Mapping code d'erreur DFM → message traduit (chargé au mount) */
    public array $dfmErrorCodes = [];

    /** true si le Job DownloadCadAssetsJob est en cours (polling actif) */
    public bool $pendingFilesDownload = false;

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

        // Charger les suggestions contextuelles selon l'état du chat
        $this->contextualSuggestions = $this->getContextualSuggestions();

        // Charger les codes d'erreur DFM pour le mapping côté frontend
        $this->dfmErrorCodes = $this->loadDfmErrorCodes();
    }

    public function updatedPartName($value): void
    {
        $this->partName = trim($value) === '' ? null : $value;
        if ($this->partName) {
            $this->chat->name = $this->partName;

            // Ne sauvegarder que si le chat existe déjà en base
            // Sinon, le nom sera persisté au premier message (send())
            if ($this->chat->exists) {
                $this->chat->save();
            }

            $this->dispatch('tolery-chat-name-updated', name: $this->partName);
        }
    }

    #[On('face-selection-state-changed')]
    public function updateComposerPlaceholder(bool $hasSelection): void
    {
        $this->composerPlaceholder = $hasSelection
            ? 'Décrivez ce que vous souhaitez modifier sur la face sélectionnée'
            : 'Décrivez le plus précisément votre pièce ou insérez un lien url ici';

        // Met à jour les suggestions selon le contexte de sélection
        $this->contextualSuggestions = $this->getContextualSuggestions($hasSelection ? 'face' : null);
    }

    /**
     * Retourne les suggestions contextuelles selon l'état du chat.
     *
     * @param  ?string  $context  Forcer un contexte ('face') ou null pour auto-détection
     * @return array<int, array{label: string, prompt: string}>
     */
    protected function getContextualSuggestions(?string $context = null): array
    {
        if ($context === 'face') {
            return [
                ['label' => 'Percer un trou', 'prompt' => 'Perce un trou de 10mm au centre de cette face'],
                ['label' => 'Ajouter une découpe', 'prompt' => 'Ajoute une découpe rectangulaire sur cette face'],
                ['label' => 'Chanfreiner les bords', 'prompt' => 'Chanfreine les bords de cette face'],
                ['label' => 'Ajouter des perçages', 'prompt' => 'Ajoute des perçages réguliers sur cette face'],
            ];
        }

        if ($this->chat->has_generated_piece) {
            return [
                ['label' => 'Modifier les dimensions', 'prompt' => 'Modifie les dimensions de la pièce'],
                ['label' => 'Ajouter des perçages', 'prompt' => 'Ajoute des perçages sur la pièce'],
                ['label' => 'Changer l\'épaisseur', 'prompt' => 'Change l\'épaisseur de la tôle'],
                ['label' => 'Ajouter un pli', 'prompt' => 'Ajoute un pli supplémentaire'],
            ];
        }

        // Chat vide : pas de suggestions (les predefined prompts dans l'empty state suffisent)
        return [];
    }

    public function rendered(): void {}

    public function render(): View
    {
        $predefinedPrompts = Cache::remember('ai-cad:predefined-prompts', 300, function () {
            $prompts = PredefinedPrompt::where('active', true)
                ->orderBy('sort_order')
                ->get()
                ->map(fn ($prompt) => [
                    'name' => $prompt->name,
                    'prompt' => $prompt->prompt_text,
                ])
                ->toArray();

            return ! empty($prompts) ? $prompts : config('ai-cad.predefined_prompts', []);
        });

        return view('ai-cad::livewire.chatbot', [
            'predefinedPrompts' => $predefinedPrompts,
            'stepMessages' => Cache::remember('ai-cad:step-messages', 300, fn () => StepMessage::getStepMessagesForFrontend()),
        ]);
    }

    #[On('updateMaterialFamily')]
    public function updateMaterialFamily(string $materialFamily): void
    {
        try {
            $validated = MaterialFamily::from($materialFamily);
            $this->chat->material_family = $validated;

            if ($this->chat->exists) {
                $this->chat->save();
            }

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

            $this->dispatch('aicad-start-stream',
                message: $userText,
                sessionId: (string) $this->chat->session_id,
                isEdit: $isEdit,
                materialChoice: $this->chat->material_family?->value ?? 'STEEL',
            );

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
        Log::info('[AICAD] saveStreamFinal', ['final_keys' => array_keys($final)]);

        $chatResponse = (string) ($final['chat_response'] ?? '');

        // Support dual-key format pour la compatibilité ascendante de l'API
        $objUrl = $final['obj_export'] ?? $final['obj_path'] ?? null;
        $stepUrl = $final['step_export'] ?? $final['step_path'] ?? null;
        $jsonModelUrl = $final['json_export'] ?? $final['json_path'] ?? null;
        $tessUrl = $final['tessellated_export'] ?? $final['tessellated_path'] ?? null;
        $techDrawingUrl = $final['technical_drawing_export'] ?? $final['technical_drawing_path'] ?? null;

        // Résolution de l'URL JSON : préférence json_export, fallback tessellated
        $resolvedJsonUrl = (is_string($jsonModelUrl) && $jsonModelUrl !== '') ? $jsonModelUrl
            : ((is_string($tessUrl) && $tessUrl !== '') ? $tessUrl : null);

        // Save session_id si changé
        if (isset($final['session_id']) && $this->chat->session_id !== $final['session_id']) {
            $this->chat->session_id = $final['session_id'];
            $this->chat->save();
            $this->chat->refresh();

            logger()->info('[AICAD] Session ID updated in DB', [
                'chat_id' => $this->chat->id,
                'new_session_id' => $this->chat->session_id,
            ]);
        }

        /** @var ChatMessage|null $asst */
        $asst = $this->findLatestAssistantMessage();

        $fallbackMessage = ($asst?->message === '[TYPING_INDICATOR]') ? null : $asst?->message;
        $messageText = $chatResponse !== '' ? $chatResponse : ($fallbackMessage ?: 'Fichier généré avec succès.');

        if ($asst) {
            $asst->message = $messageText;
        } else {
            $asst = $this->storeMessage(ChatMessage::ROLE_ASSISTANT, $messageText);
        }

        // Télécharge le JSON synchroniquement et le stocke localement.
        // Three.js accède ensuite via la route proxy Laravel (même domaine, pas de CORS).
        // En cas d'échec, l'URL externe reste en fallback (servie via la route proxy).
        if ($resolvedJsonUrl) {
            try {
                $apiKey = config('ai-cad.api.key');
                $jsonResponse = Http::when($apiKey, fn ($req) => $req->withToken($apiKey))
                    ->timeout(30)
                    ->get($resolvedJsonUrl);

                if ($jsonResponse->successful()) {
                    $folder = $this->chat->getStorageFolder();
                    $filename = uniqid('cad_json_').'.json';
                    $localPath = "{$folder}/{$filename}";
                    Storage::put($localPath, $jsonResponse->body());
                    $asst->ai_json_edge_path = $localPath;
                    logger()->info("[AICAD] JSON téléchargé et stocké: {$resolvedJsonUrl} → {$localPath}");
                } else {
                    logger()->warning('[AICAD] Échec download JSON', ['status' => $jsonResponse->status(), 'url' => $resolvedJsonUrl]);
                    $asst->ai_json_edge_path = $resolvedJsonUrl; // Fallback URL externe (proxifiée par CadFileController)
                }
            } catch (\Exception $e) {
                logger()->warning('[AICAD] Exception download JSON', ['url' => $resolvedJsonUrl, 'error' => $e->getMessage()]);
                $asst->ai_json_edge_path = $resolvedJsonUrl; // Fallback URL externe (proxifiée par CadFileController)
            }
        }

        // OBJ/STEP/PDF seront téléchargés en background par DownloadCadAssetsJob
        $asst->cad_files_ready = false;
        $asst->save();

        // Marquer la première génération réussie
        $isSuccessfulGeneration = $resolvedJsonUrl || $objUrl || $stepUrl;
        if ($isSuccessfulGeneration && ! $this->chat->has_generated_piece) {
            $this->chat->has_generated_piece = true;
            $this->chat->save();

            Log::info('[AICAD] Première pièce générée avec succès', ['chat_id' => $this->chat->id]);
        }

        // Dispatch du Job background pour OBJ, STEP, PDF uniquement
        $urlsForJob = array_filter([
            'obj' => (is_string($objUrl) && $objUrl !== '') ? $objUrl : null,
            'step' => (is_string($stepUrl) && $stepUrl !== '') ? $stepUrl : null,
            'pdf' => (is_string($techDrawingUrl) && $techDrawingUrl !== '') ? $techDrawingUrl : null,
        ]);

        if (! empty($urlsForJob)) {
            DownloadCadAssetsJob::dispatch($asst->id, $urlsForJob, $this->chat->getStorageFolder());
            $this->pendingFilesDownload = true;
        } else {
            $asst->cad_files_ready = true;
            $asst->save();
        }

        $this->messages = $this->mapDbMessagesToArray();
        $this->contextualSuggestions = $this->getContextualSuggestions();

        $this->dispatch('tolery-chat-append');

        // Dispatch vers Three.js via la route proxy Laravel (même domaine, pas de CORS,
        // pas de problème d'accessibilité du Storage)
        $this->dispatch('jsonEdgesLoaded', jsonPath: route('ai-cad.file.json', ['messageId' => $asst->id]));

        // Export links initiaux (OBJ/STEP/PDF seront null jusqu'à la fin du Job)
        $this->dispatchExportLinks($asst);

        logger()->info('[AICAD] saveStreamFinal: Job dispatché pour téléchargements', [
            'chat_id' => $this->chat->id,
            'pending_keys' => array_keys($urlsForJob),
        ]);
    }

    /**
     * Vérifie si le Job DownloadCadAssetsJob a terminé ses téléchargements.
     * Appelé par wire:poll toutes les 5s tant que $pendingFilesDownload est true.
     */
    public function checkFilesReady(): void
    {
        if (! $this->pendingFilesDownload) {
            return;
        }

        $asst = $this->findLatestAssistantMessage();

        if (! $asst) {
            $this->pendingFilesDownload = false;

            return;
        }

        $asst->refresh();

        if (! $asst->cad_files_ready) {
            return; // Continuer le polling
        }

        $this->pendingFilesDownload = false;
        $this->dispatchExportLinks($asst);
        $this->messages = $this->mapDbMessagesToArray();
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
        return $this->chat->messages()->with('user')
            ->where('message', '!=', '[TYPING_INDICATOR]')
            ->orderBy('created_at')->get()
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

    protected function storeMessage(string $role, string $content): ChatMessage
    {
        return $this->chat->messages()->create([
            'role' => $role,
            'message' => $content,
            'user_id' => auth()->id(),
        ]);
    }

    /**
     * Simule une réponse du bot pour tester l'effet typewriter et les suggestions contextuelles.
     * Crée un typing indicator puis le remplace par un texte de test après un court délai.
     * À utiliser uniquement en local (APP_DEBUG=true).
     */
    public function simulateBotResponse(): void
    {
        if (! config('app.debug')) {
            return;
        }

        // Crée le chat si nécessaire (ghost chat)
        if (! $this->chat->exists) {
            /** @var ChatUser $user */
            $user = auth()->user();
            $this->chat->team()->associate($user->team);
            $this->chat->user()->associate($user);
            $this->chat->name = $this->partName;
            $this->chat->save();
        }

        // 1. Ajoute un message user fictif
        $mUser = $this->storeMessage('user', 'Message de test pour le typewriter');
        $mUser->load('user');
        $this->messages[] = [
            'role' => 'user',
            'content' => 'Message de test pour le typewriter',
            'created_at' => Carbon::parse($mUser->created_at)->toIso8601String(),
            'screenshot_url' => null,
            'user' => $mUser->user,
        ];
        $this->dispatch('tolery-chat-append');

        // 2. Placeholder assistant avec typing indicator
        $mAsst = $this->storeMessage('assistant', '[TYPING_INDICATOR]');
        $mAsst->load('user');
        $this->messages[] = [
            'role' => 'assistant',
            'content' => '[TYPING_INDICATOR]',
            'created_at' => Carbon::parse($mAsst->created_at)->toIso8601String(),
            'screenshot_url' => null,
            'user' => $mAsst->user,
        ];
        $this->dispatch('tolery-chat-append');

        // 3. Après 2s, remplace par le vrai texte via saveStreamFinal
        $sampleText = "Voici une réponse simulée du bot ToleryCAD pour tester l'effet typewriter.\n\n"
            .'La pièce a été générée avec succès. Vous pouvez maintenant modifier les dimensions, '
            ."ajouter des perçages ou changer l'épaisseur de la tôle.\n\n"
            ."N'hésitez pas à me demander des modifications supplémentaires !";

        $this->js('setTimeout(() => { $wire.saveStreamFinal({ chat_response: '.json_encode($sampleText).' }) }, 2000)');
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
        } catch (\Exception) {
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
