<div class="space-y-6">
    {{-- En-tête avec infos chat --}}
    <flux:card>
        <div class="flex items-start justify-between">
            <div>
                <flux:heading size="lg">{{ $chat->name ?: 'Conversation sans nom' }}</flux:heading>
                <div class="mt-2 space-y-1 text-sm text-zinc-600 dark:text-zinc-400">
                    <p><strong>Équipe:</strong> {{ $chat->team?->name ?? '-' }}</p>
                    <p><strong>Matériau:</strong> {{ $chat->material_family?->label() ?? '-' }}</p>
                    <p><strong>Créée le:</strong> {{ $chat->created_at->format('d/m/Y H:i') }}</p>
                    @if($chat->trashed())
                        <p class="text-red-600"><strong>Supprimée le:</strong> {{ $chat->deleted_at->format('d/m/Y H:i') }}</p>
                    @endif
                </div>
            </div>
            <div class="flex items-center gap-2">
                @if($chat->has_generated_piece)
                    <flux:badge color="green">Pièce générée</flux:badge>
                @endif
                @if($chat->trashed())
                    <flux:badge color="red">Supprimée</flux:badge>
                @else
                    <flux:badge color="green">Active</flux:badge>
                @endif
            </div>
        </div>
    </flux:card>

    {{-- Messages --}}
    <flux:card>
        <flux:heading size="md" class="mb-4">Messages ({{ $chat->messages->count() }})</flux:heading>

        <div class="space-y-4">
            @forelse($chat->messages as $message)
                <div wire:key="message-{{ $message->id }}" class="rounded-lg border p-4 dark:border-zinc-700 {{ $message->role === 'user' ? 'bg-blue-50 dark:bg-blue-900/20' : 'bg-zinc-50 dark:bg-zinc-800' }}">
                    <div class="mb-2 flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            @if($message->role === 'user')
                                <flux:badge color="blue" size="sm">Utilisateur</flux:badge>
                                @if($message->user)
                                    <span class="text-sm text-zinc-600 dark:text-zinc-400">{{ $message->user->name }}</span>
                                @endif
                            @else
                                <flux:badge color="purple" size="sm">Assistant</flux:badge>
                                @if($message->getVersionLabel())
                                    <flux:badge color="green" size="sm">{{ $message->getVersionLabel() }}</flux:badge>
                                @endif
                            @endif
                        </div>
                        <span class="text-xs text-zinc-500">{{ $message->created_at->format('d/m/Y H:i:s') }}</span>
                    </div>

                    {{-- Texte du message --}}
                    @if($message->message)
                        <div class="prose prose-sm dark:prose-invert max-w-none mb-3">
                            {!! nl2br(e($message->message)) !!}
                        </div>
                    @endif

                    {{-- Screenshot de la pièce générée --}}
                    @if($message->ai_screenshot_path)
                        <div class="mt-3 border-t pt-3 dark:border-zinc-700">
                            <p class="mb-2 text-xs font-medium text-zinc-500">Aperçu de la pièce:</p>
                            <div class="rounded-lg overflow-hidden border border-zinc-200 dark:border-zinc-700 inline-block">
                                <img src="{{ $message->getScreenshotUrl() }}" 
                                     alt="Screenshot {{ $message->getVersionLabel() ?? 'pièce' }}" 
                                     class="max-w-md w-full h-auto">
                            </div>
                        </div>
                    @endif

                    {{-- Fichiers générés (CAD, STEP, PDF) --}}
                    @if($message->ai_cad_path || $message->ai_step_path || $message->ai_technical_drawing_path || $message->ai_json_edge_path)
                        <div class="mt-3 border-t pt-3 dark:border-zinc-700">
                            <p class="mb-2 text-xs font-medium text-zinc-500">Fichiers générés:</p>
                            <div class="flex flex-wrap gap-2">
                                @if($message->ai_step_path)
                                    <flux:badge size="sm" color="green">
                                        <flux:icon.document-arrow-down class="size-3 mr-1" />
                                        STEP
                                    </flux:badge>
                                @endif
                                @if($message->ai_cad_path)
                                    <flux:badge size="sm" color="blue">
                                        <flux:icon.document-arrow-down class="size-3 mr-1" />
                                        OBJ
                                    </flux:badge>
                                @endif
                                @if($message->ai_technical_drawing_path)
                                    <flux:badge size="sm" color="purple">
                                        <flux:icon.document-arrow-down class="size-3 mr-1" />
                                        PDF (Plan technique)
                                    </flux:badge>
                                @endif
                                @if($message->ai_json_edge_path)
                                    <flux:badge size="sm" color="zinc">
                                        <flux:icon.document-arrow-down class="size-3 mr-1" />
                                        JSON
                                    </flux:badge>
                                @endif
                            </div>
                        </div>
                    @endif
                </div>
            @empty
                <div class="py-8 text-center text-zinc-500">
                    Aucun message dans cette conversation.
                </div>
            @endforelse
        </div>
    </flux:card>
</div>
