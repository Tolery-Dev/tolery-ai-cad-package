@push('styles')

    @php
    $version = '6';
    @endphp

    <link href="{{ asset('vendor/ai-cad/assets/app.css') }}?{{$version}}" rel="stylesheet" />
    <script src="{{ asset('vendor/ai-cad/assets/app.js') }}?{{$version}}" defer></script>
@endpush


<div class="chatbot">
    <div class="chatbot__message_list">

        <div class="chatbot__message_list_wrapper">

            @foreach($chatMessages as $message)
                <div class="{{ $message->user_id ? 'chatbot__message-client' : 'chatbot__message-bot' }}">
                    <p>{{$message->message}}</p>
                </div>
            @endforeach

            @if($waitingForAnswer)
                <div wire:poll="getAnswer">
                    <p class="chatbot__loading">Interrogation de l'IA en cours</p>
                </div>
            @endif
        </div>

        <div class="chatbot__form">
            <textarea wire:model="entry" placeholder="votre message"></textarea>
            <input type="file" wire:model="pdfFile"/>
            <button wire:click="submitEntry" @if($waitingForAnswer) disabled="disabled" @endif>Soumettre</button>
        </div>
        <div id="chatbot-anchor"></div>
    </div>
    <div class="chatbot__viewer">
        <div id="viewer" style="width: 100%; height: 600px;" wire:ignore>
        </div>
    </div>

    <div class="chatbot__config">
        <h2>Configuration</h2>

        @if($objectToConfigId)
            Object {{$objectToConfigId}}
        @else
            <livewire:chat-config :chat="$chat"/>
        @endif
    </div>
</div>
