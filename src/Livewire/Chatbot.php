<?php

namespace Tolery\AiCad\Livewire;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\WithFileUploads;
use Log;
use Storage;
use Tolery\AiCad\Jobs\GetAICADResponse;
use Tolery\AiCad\Models\Chat;
use Tolery\AiCad\Models\ChatUser;

class Chatbot extends Component
{
    use WithFileUploads;

    public ?Chat $chat = null;

    public Collection $chatMessages;

    public ?string $errorMessage = null;

    public string $entry = '';

    public Carbon $lastTimeAnswer;

    public bool $waitingForAnswer = false;

    #[Validate('image|max:1024')] // 1MB Max
    public $pdfFile;

    public function mount(): void
    {
        if (! $this->chat) {
            $chat = new Chat;

            /** @var ChatUser $user */
            $user = auth()->user();
            $chat->team()->associate($user->team);
            $chat->user()->associate($user);
            $chat->save();
            $this->chat = $chat;
        }

        $this->chatMessages = $this->chat->messages;
        $this->lastTimeAnswer = $this->chat->messages->isEmpty() ? now() : $this->chat->messages->last()->created_at;

        $objToDisplay = $this->chat->messages->isEmpty() ? null : $this->chat->messages->last()->getJSONEdgeUrl();
        if ($objToDisplay) {
            $this->dispatch('jsonLoaded', jsonPath: $objToDisplay);
        }
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
        $this->getAPIResponse();

        $this->entry = '';
        $this->chatMessages = $this->chat->messages()->get();
    }

    public function getAnswer(): void
    {
        // On va regarder si une nouvelle réponse est arrivé et la renvoyer

        $lastAnswer = $this->chat->messages()->whereNull('user_id')->latest()->first();

        if ($lastAnswer && $lastAnswer->created_at > $this->lastTimeAnswer) {
            $this->chatMessages = $this->chat->messages()->get();
            $this->lastTimeAnswer = $lastAnswer->created_at;
            if ($objToDisplay = $lastAnswer->getJSONEdgeUrl()) {
                $this->dispatch('jsonLoaded', jsonPath: $objToDisplay);
            }

            $this->waitingForAnswer = false;
        }
    }

    public function render(): View
    {
        return view('ai-cad::livewire.chatbot');
    }

    private function getAPIResponse(): void
    {
        $pdfUrl = null;

        if ($this->pdfFile) {
            $name = Str::slug($this->pdfFile->getClientOriginalName());
            $pdfPath = $this->pdfFile->storeAs(path: $this->chat->getStorageFolder(), name: $name);
            $pdfUrl = Storage::url($pdfPath);
            Log::info('getAPIResponse : '.$pdfUrl);
        }
        GetAICADResponse::dispatch($this->chat, $this->entry, $pdfUrl);
    }
}
