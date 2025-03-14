<div class="chatbot grid grid-cols-3 overflow-hidden h-full">
    <div class="chatbot__message_list flex flex-col overflow-hidden">

        <div class="chatbot__message_list_wrapper overflow-y-auto p-6">

            @foreach($chatMessages as $message)
                <div class="w-[80%] mb-6 p-6 rounded-xl {{ $message->user_id ? 'chatbot__message-client ml-auto bg-gray-100' : 'chatbot__message-bot bg-gray-300' }}">
                    <p>{{$message->message}}</p>
                </div>
            @endforeach

            @if($waitingForAnswer)
                <div wire:poll="getAnswer">
                    <p class="chatbot__loading">Interrogation de l'IA en cours</p>
                </div>
            @endif
        </div>

        <div class="chatbot__form p-6">
            <flux:textarea wire:model="entry" placeholder="votre message" />
            <input type="file" wire:model="pdfFile"/>
            <button wire:click="submitEntry" @if($waitingForAnswer) disabled="disabled" @endif>Soumettre</button>
        </div>
        <div id="chatbot-anchor"></div>
    </div>
    <div class="chatbot__viewer">
        <div id="viewer" style="width: 100%; height: 100%;" wire:ignore>
        </div>
    </div>

    <div class="chatbot__config p-6">
        <flux:heading size="xl" level="2" class="mb-6">Configuration</flux:heading>

        @if($objectToConfigId)
            Object {{$objectToConfigId}}
        @else
            <livewire:chat-config :chat="$chat"/>
        @endif
    </div>
</div>
