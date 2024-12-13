@push('styles')
    <link href="{{ asset('vendor/ai-cad/assets/app-CWlVyv0T.css') }}" rel="stylesheet" />
    <script src="{{ asset('vendor/ai-cad/assets/app-CZSWL30P.js') }}" defer></script>
@endpush


<div class="chatbot">
    <div class="chatbot__message_list">

        @foreach($chatMessages as $message)
            <div class="{{ $message->user ? 'chatbot__message-client' : 'chatbot__message-bot' }}">
                <p>{{$message->message}}</p>

                @if($message->getObjUrl())
                    <a href="{{$message->getObjUrl()}}">{{$message->getObjName()}}</a>
                @endif
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
        <div id="chatbot-anchor"></div>
    </div>
    <div class="chatbot__viewer">
        <div id="viewer" style="width: 100%; height: 600px;" wire:ignore>
        </div>
    </div>
</div>
