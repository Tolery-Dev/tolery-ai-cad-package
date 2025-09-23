<?php

namespace Tolery\AiCad\Livewire;

use Illuminate\Cache\RateLimiter;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Livewire\Attributes\On;
use Livewire\Component;
use Tolery\AiCad\Models\Chat;
use Tolery\AiCad\Models\ChatMessage;
use Tolery\AiCad\Services\AICADClient;

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

    public function send(AICADClient $api, RateLimiter $limiter): void
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
            ];
            $this->dispatch('tolery-chat-append');

            // Placeholder assistant "AI thinking…"
            $mAsst = $this->storeMessage('assistant', 'AI thinking…');
            $this->messages[] = [
                'role' => 'assistant',
                'content' => 'AI thinking…',
                'created_at' => Carbon::parse($mAsst->created_at)->toIso8601String(),
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
     *   technical_drawing_export?:?string,
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
        $jsonModelUrl = $final['json_export'] ?? null; // JSON principal pour affichage
        $tessUrl = $final['tessellated_export'] ?? null; // JSON tessellé (héritage)
        $techDrawingUrl = $final['technical_drawing_export'] ?? null; // plan technique

        // Save session_id
        if ($final['session_id'] && $this->chat->session_id !== $final['session_id']) {
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
        $this->applyFinalAssetsToMessage($asst, $objUrl, $jsonModelUrl, $tessUrl, $techDrawingUrl);

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
     * Applique les exports finaux (OBJ, JSON edges, plan technique) au message.
     * Préférence: json_export, fallback: tessellated_export.
     */
    private function applyFinalAssetsToMessage(
        ChatMessage $asst,
        mixed $objUrl,
        mixed $jsonModelUrl,
        mixed $tessUrl,
        mixed $techDrawingUrl
    ): void {
        if (is_string($objUrl) && $objUrl !== '') {
            // Convention existante: ai_cad_path pour l'URL OBJ
            $asst->ai_cad_path = $objUrl;
        }

        // Priorité à json_export pour l'affichage 3D JSON (fallback compat sur tessellated_export)
        if (is_string($jsonModelUrl) && $jsonModelUrl !== '') {
            $asst->ai_json_edge_path = $jsonModelUrl;
        } elseif (is_string($tessUrl) && $tessUrl !== '') {
            $asst->ai_json_edge_path = $tessUrl;
        }

        if (is_string($techDrawingUrl) && $techDrawingUrl !== '') {
            $asst->ai_technical_drawing_path = $techDrawingUrl;
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

            return;
        }

        // Fallback OBJ
        if ($asst->ai_cad_path) {
            $this->dispatch('objLoaded', objPath: $asst->getObjUrl());
        }
    }

    /* ----------------- Helpers ----------------- */

    protected function mapDbMessagesToArray(): array
    {
        return $this->chat->messages()->orderBy('created_at')->get()
            ->map(fn (ChatMessage $m) => [
                'role' => $m->role,
                'content' => (string) $m->message,
                'created_at' => Carbon::parse($m->created_at)->toIso8601String(),
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
}
