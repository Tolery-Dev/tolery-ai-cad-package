{{--
    #2374 — Modal affichée au clic sur « Télécharger votre fichier » quand les
    assets (STEP/PDF) sont encore en cours de téléchargement en background
    (DownloadCadAssetsJob). Le polling (wire:poll="checkFilesReady", voir
    chatbot.blade.php) déclenche automatiquement le téléchargement et ferme
    cette modal dès que cad_files_ready passe à true.

    On bloque la fermeture par clic extérieur / Échap pour éviter de couper le
    polling par mégarde : la fermeture explicite passe par cancelPendingDownload().
--}}
<flux:modal
    name="preparing-download"
    :open="$showPreparingModal"
    :dismissible="false"
    :closable="false"
    class="w-full max-w-md">
    <div class="flex flex-col items-center gap-4 py-4 text-center">
        <flux:icon.arrow-path class="size-12 text-violet-600 animate-spin" />

        <div class="space-y-1">
            <flux:heading size="lg">Vos fichiers sont en cours de préparation</flux:heading>
            <flux:subheading>
                Le téléchargement démarrera automatiquement dès qu'ils seront prêts.
            </flux:subheading>
        </div>

        <flux:button variant="ghost" size="sm" wire:click="cancelPendingDownload">
            Annuler
        </flux:button>
    </div>
</flux:modal>
