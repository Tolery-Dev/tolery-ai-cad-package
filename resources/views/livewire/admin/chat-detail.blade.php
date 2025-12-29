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
                            @endif
                        </div>
                        <span class="text-xs text-zinc-500">{{ $message->created_at->format('d/m/Y H:i:s') }}</span>
                    </div>

                    <div class="prose prose-sm dark:prose-invert max-w-none">
                        {!! nl2br(e($message->content)) !!}
                    </div>

                    {{-- Fichiers attachés --}}
                    @if($message->attachments && count($message->attachments) > 0)
                        <div class="mt-3 border-t pt-3 dark:border-zinc-700">
                            <p class="mb-2 text-xs font-medium text-zinc-500">Fichiers attachés:</p>
                            <div class="flex flex-wrap gap-2">
                                @foreach($message->attachments as $attachment)
                                    <flux:badge size="sm" color="zinc">
                                        {{ $attachment['name'] ?? 'Fichier' }}
                                    </flux:badge>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    {{-- Fichiers générés --}}
                    @if($message->generated_files && count($message->generated_files) > 0)
                        <div class="mt-3 border-t pt-3 dark:border-zinc-700">
                            <p class="mb-2 text-xs font-medium text-zinc-500">Fichiers générés:</p>
                            <div class="flex flex-wrap gap-2">
                                @foreach($message->generated_files as $file)
                                    <flux:badge size="sm" color="green">
                                        {{ $file['type'] ?? 'Fichier' }}
                                    </flux:badge>
                                @endforeach
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
