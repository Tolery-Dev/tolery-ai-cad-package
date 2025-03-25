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

    <div class="chatbot__config p-6 flex flex-col space-y-6">
        <flux:heading size="xl" level="2" class="mb-6">Configuration</flux:heading>

        @if($objectToConfigId)
            Object {{$objectToConfigId}}
        @else
            <livewire:chat-config :chat="$chat"/>
        @endif


        <flux:separator text="Configuration de l'affichage" />

        <div class="space-y-6">
            <flux:switch wire:model.live="edgesShow" label="Afficher les contours" />

            @if($edgesShow)
                <flux:input wire:model.live="edgesColor" label="Couleurs des contours" type="color" />
            @endif
        </div>

            <div class="flex grow flex-col justify-end items-end">

            <flux:modal.trigger name="buy-file">
                <flux:button variant="primary">Acheter la piéce</flux:button>
            </flux:modal.trigger>

            <flux:modal name="buy-file" variant="flyout" class="rounded-l-xl">
                <div class="space-y-6">
                    <div>
                        <flux:heading size="lg" level="2" class="font-bold">Acheter le fichier</flux:heading>
                    </div>

                    <div class="p-6 rounded-xl border">
                        <div>

                            <img class="w-64 h-52 p-2.5 mx-auto" src="https://placehold.co/262x201" />
                        </div>

                        <div class="flex items-center space-x-6 bg-gray-100 p-6 rounded-xl text-sm" >
                            <strong>ABCDE - 2345</strong>
                            <div>
                                <strong>Dimension pièce : </strong>
                                <span>100 x 25 x 82 x ep 4 mm</span>
                            </div>
                            <div>
                                <strong>Dimension à plat : </strong>
                                <span>25 x 251 mm</span>
                            </div>
                            <div>
                                <strong>Pliages : </strong>
                                <span>4</span>
                            </div>
                        </div>
                    </div>

                    <div>
                        @hasLimit
                            @php
                                $team = auth()->user()->team;
                                $limit = $team->limits->first();
                                $product = $team->getSubscriptionProduct()
                            @endphp

                            <p>Vous avec un abonement en cours</p>
                            <p>Vous avez consommé {{ $limit->used_amount }}/ {{$product->files_allowed }}</p>
                        @else
                            <p>Vous n'avec pas d'abonement</p>
                        @endif
                    </div>
                </div>
            </flux:modal>
        </div>
    </div>
</div>
