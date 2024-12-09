@push('styles')
    <link href="{{ asset('vendor/ai-cad/assets/chatbot-Bq2PEd0x.css') }}" rel="stylesheet" />
@endpush


<div class="chatbot">
    @foreach($chatMessages as $message)
        <div class="{{ $message->user ? 'chatbot__message-client' : 'chatbot__message-bot' }}">
            <p>{{$message->user ? $message->user->fullname : 'bot'}}</p>
            <p>{{$message->message}}</p>
        </div>
    @endforeach

    @if($waitingForAnswer)
        <div wire:poll="getAnswer">
            <p class="chatbot__loading">Interrogation de l'IA en cours</p>
        </div>
    @endif

    <div class="chatbot__form">
        <textarea wire:model="entry"></textarea>
        <button wire:click="submitEntry" @if($waitingForAnswer) disabled="disabled" @endif>Test</button>
    </div>

</div>