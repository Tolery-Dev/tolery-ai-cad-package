<?php

namespace Tolery\AiCad\Livewire;

use Carbon\Carbon;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Livewire\Component;
use Tolery\AiCad\Jobs\GetAICADResponse;
use Tolery\AiCad\Models\Chat;

class Chatbot extends Component
{
    public ?Chat $chat = null;

    public Collection $chatMessages;

    public ?string $errorMessage = null;

    public string $entry = '';

    public Carbon $lastTimeAnswer;

    public bool $waitingForAnswer = false;

    public function mount(): void
    {
        if(! $this->chat) {
            $chat = new Chat();
            $chat->team()->associate(auth()->user()->team);
            $chat->user()->associate(auth()->user());
            $chat->save();
            $this->chat = $chat;
        }

        $this->chatMessages = $this->chat->messages;
        $this->lastTimeAnswer = $this->chat->messages->isEmpty() ? now() : $this->chat->messages->last()->created_at;
    }

    public function submitEntry(): void
    {

        $message = $this->entry;
        // On ajoute le nouveau message a la conversation
        $this->chat->messages()->create([
           'message' => $message,
           'user_id' => auth()->id(),
        ]);

        $this->waitingForAnswer = true;

        // On va récupérer la réponse
        $this->getAPIResponse($message);

        $this->entry = '';
        $this->chatMessages = $this->chat->messages()->get();
    }

    public function getAnswer(): void
    {
       // On va regarder si une nouvelle réponse est arrivé et la renvoyer

        $lastAnswer = $this->chat->messages()->whereNull('user_id')->latest()->first();

        if($lastAnswer->created_at > $this->lastTimeAnswer) {
            $this->chatMessages = $this->chat->messages()->get();
            $this->lastTimeAnswer = $lastAnswer->created_at;
            $this->waitingForAnswer = false;
        }
    }

    public function render(): View
    {
        return view('ai-cad::livewire.chatbot');
    }

    private function getAPIResponse(string $entry): void
    {
        GetAICADResponse::dispatch($this->chat, $entry);
    }
}
