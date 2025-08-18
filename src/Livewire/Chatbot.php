<?php

namespace Tolery\AiCad\Livewire;

use Illuminate\Cache\RateLimiter;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\On;
use Livewire\Component;
use Throwable;
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
    protected int $httpTimeoutSec = 60;

    protected int $maxRetries = 1;

    protected int $ratePerMinute = 10;    // anti-spam

    protected int $lockSeconds = 12;    // anti double submit

    public function mount(): void
    {
        // Si nouveau chat, on s’assure qu’il existe en base
        if (! $this->chat->exists) {
            $chat = new Chat;
            $chat->session_id = request()->session()->id();
            /** @var ChatUser $user */
            $user = auth()->user();
            $chat->team()->associate($user->team);
            $chat->user()->associate($user);
            $chat->save();
            $chat->name ??= 'Nouvelle conversation';
            $chat->save();
            $this->chat = $chat;
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
            // OBJ pour l'affichage “classique”
            $this->dispatch('jsonLoaded', objPath: $objToDisplay->getObjUrl());

            // JSON tessellé pour la sélection de faces
            $jsonUrl = $objToDisplay->getJSONEdgeUrl();
            if ($jsonUrl) {
                $this->dispatch('jsonEdgesLoaded', jsonPath: $jsonUrl);
            }
        }
    }

    public function updatedEdgesShow($value)
    {
        $this->dispatch('toggleShowEdges', show: $value);
    }

    public function updatedEdgesColor($value)
    {
        $this->dispatch('updatedEdgeColor', color: $value);
    }

    public function render()
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
            $this->dispatch('tolery-chat:append');

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

            // Persiste + UI
            $mUser = $this->storeMessage('user', $userText);
            $this->messages[] = [
                'role' => 'user',
                'content' => $userText,
                'created_at' => Carbon::parse($mUser->created_at)->toIso8601String(),
            ];
            $this->dispatch('tolery-chat:append');

            // Placeholder assistant
            $mAsst = $this->storeMessage('assistant', '');
            $this->messages[] = [
                'role' => 'assistant',
                'content' => '',
                'created_at' => Carbon::parse($mAsst->created_at)->toIso8601String(),
            ];
            $this->streamingIndex = array_key_last($this->messages);
            $this->lastRefreshAt = microtime(true);

            // Contexte -> messages pour l’API (les 16 derniers)
            $apiMessages = $this->buildMessagesForApi();

            // Streaming (avec retry léger)
            $jsonUrl = null;
            $attempts = 0;
            RETRY: try {
                foreach ($api->chatToCadStream(
                    messages: $apiMessages,
                    projectId: (string) $this->chat->id,
                    timeoutSec: $this->httpTimeoutSec
                ) as $ev) {
                    $type = $ev['type'] ?? null;
                    if ($type === 'delta' && ($chunk = $ev['data'] ?? '') !== '') {
                        $this->appendAssistantDelta($chunk, $mAsst);
                    }
                    if ($type === 'json_edges' && is_string($ev['url'] ?? null)) {
                        $jsonUrl = $ev['url'];
                    }
                    if ($type === 'result') {
                        $final = (string) ($ev['assistant_message'] ?? '');
                        if ($final !== '') {
                            $this->setAssistantFull($final, $mAsst);
                        }
                        $jsonUrl = $ev['json_edges_url'] ?? $jsonUrl;
                    }
                }
            } catch (Throwable $e) {
                if ($attempts < $this->maxRetries) {
                    $attempts++;
                    goto RETRY;
                }
                // fallback non-streaming “one-shot”
                $one = $api->chatToCad(messages: $apiMessages, projectId: (string) $this->chat->id, timeoutSec: $this->httpTimeoutSec);
                $final = (string) ($one['assistant_message'] ?? '');
                if ($final !== '') {
                    $this->setAssistantFull($final, $mAsst);
                }
                $jsonUrl = $one['json_edges_url'] ?? $jsonUrl;
            }

            if (is_string($jsonUrl) && $jsonUrl !== '') {
                $this->dispatch('jsonEdgesLoaded', jsonPath: $jsonUrl);
                $mAsst->ai_json_edge_path = $jsonUrl;
                $mAsst->save();
            }
        } finally {
            $this->isProcessing = false;
            optional($lock)->release();
            $this->dispatch('tolery-chat:append');
        }
    }

    #[On('chatObjectClick')]
    public function handleObjectClick(?string $objectId = null, ?AICADClient $api = null): void
    {
        if (! $objectId) {
            $this->appendAssistant('Aucune face sélectionnée.');

            return;
        }

        $this->storeMessage('user', "[Sélection de face] {$objectId}");
        $this->messages[] = [
            'role' => 'user',
            'content' => "[Sélection de face] {$objectId}",
            'created_at' => now()->toIso8601String(),
        ];
        if (! $api) {
            return;
        }

        $this->isProcessing = true;
        $mAsst = $this->storeMessage('assistant', '');
        $this->messages[] = [
            'role' => 'assistant',
            'content' => '',
            'created_at' => now()->toIso8601String(),
        ];
        $this->streamingIndex = array_key_last($this->messages);
        $this->lastRefreshAt = microtime(true);

        try {
            $apiMessages = $this->buildMessagesForApi(prefixAction: "ACTION: select_face id={$objectId}");
            foreach ($api->chatToCadStream($apiMessages, (string) $this->chat->id, $this->httpTimeoutSec) as $ev) {
                $type = $ev['type'] ?? null;
                if ($type === 'delta' && ($chunk = $ev['data'] ?? '') !== '') {
                    $this->appendAssistantDelta($chunk, $mAsst);
                }
                if ($type === 'result') {
                    $final = (string) ($ev['assistant_message'] ?? '');
                    if ($final !== '') {
                        $this->setAssistantFull($final, $mAsst);
                    }
                    if ($url = ($ev['json_edges_url'] ?? null)) {
                        $this->dispatch('jsonEdgesLoaded', jsonPath: $url);
                        $mAsst->ai_json_edge_path = $url;
                        $mAsst->save();
                    }
                }
                if ($type === 'json_edges' && is_string($ev['url'] ?? null)) {
                    $this->dispatch('jsonEdgesLoaded', jsonPath: $ev['url']);
                }
            }
        } finally {
            $this->isProcessing = false;
            $this->dispatch('tolery-chat:append');
        }
    }

    public function resetConversation(): void
    {
        DB::transaction(fn () => $this->chat->messages()->delete());
        $this->messages = [];
        $this->appendAssistant('Conversation réinitialisée. Décrivez la pièce à générer…');
        $this->dispatch('tolery-chat:append');
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

    /** Prépare le payload "messages" pour l’API (16 derniers). Optionnel: préfixe "ACTION: ..."  */
    protected function buildMessagesForApi(?string $prefixAction = null): array
    {
        $rows = $this->chat->messages()->orderByDesc('id')->limit(16)->get()->reverse();
        $messages = $rows->map(fn (ChatMessage $m) => [
            'role' => $m->role,             // 'user' | 'assistant'
            'content' => (string) $m->message,
        ])->values()->all();

        if ($prefixAction) {
            $messages[] = ['role' => 'user', 'content' => $prefixAction];
        }

        return $messages;
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
            $this->dispatch('tolery-chat:append');
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

        $this->dispatch('tolery-chat:append');
        $this->dispatch('$refresh');
    }
}
