<div>
    {{-- Stub minimal du composant chatbot pour les tests Livewire (#2374).
         Évite de dépendre des composants Flux Pro et des routes de l'app hôte. --}}
    @if ($pendingFilesDownload)
        <div wire:poll.5000ms="checkFilesReady"></div>
    @endif
</div>
