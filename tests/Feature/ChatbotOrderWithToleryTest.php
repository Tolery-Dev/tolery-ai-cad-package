<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\View;
use Livewire\Livewire;
use Tolery\AiCad\Livewire\Chatbot;
use Tolery\AiCad\Models\Chat;
use Tolery\AiCad\Models\ChatTeam;
use Tolery\AiCad\Models\ChatUser;
use Tolery\AiCad\Services\FileAccessService;

/**
 * #2381 — Bouton « Chiffrer et commander la pièce » : envoie la pièce générée
 * dans un devis Tolery (le chiffrage/commande vit dans l'app hôte). Même gate
 * d'accès que le téléchargement : abonnement (essai/payant) ou pièce achetée,
 * sinon modal d'achat/abonnement.
 */
beforeEach(function () {
    config(['ai-cad.chat_team_model' => ChatTeam::class]);
    config(['ai-cad.chat_user_model' => ChatUser::class]);

    // Vue stub minimale : évite de dépendre de Flux Pro et des routes hôtes
    // pour rendre le composant pendant les tests Livewire.
    View::prependNamespace('ai-cad', __DIR__.'/../stubs/ai-cad');
    Storage::fake();

    // Route hôte simulée (dans mn-tolery : ChatController@createOrder).
    Route::get('/tolerycad/chatbot/{chat}/order', fn () => 'ok')
        ->name('client.tolerycad.create-order');
});

/**
 * Force le verdict d'accès aux fichiers (abonnement / achat) pour isoler le gate
 * du composant de la logique réelle de FileAccessService.
 */
function fakeOrderFileAccess(bool $canDownload): void
{
    app()->instance(FileAccessService::class, new class($canDownload) extends FileAccessService
    {
        public function __construct(private bool $canDownload) {}

        public function canDownloadChat(ChatTeam $team, Chat $chat): array
        {
            return ['can_download' => $this->canDownload];
        }
    });
}

it('redirige vers la route de chiffrage hôte quand l\'utilisateur a un accès', function () {
    fakeOrderFileAccess(true);

    $team = ChatTeam::factory()->create();
    $user = ChatUser::create(['team_id' => $team->id]);
    Auth::setUser($user);

    $chat = Chat::factory()->create([
        'team_id' => $team->id,
        'user_id' => $user->id,
    ]);

    Livewire::test(Chatbot::class, ['chat' => $chat])
        ->call('orderWithTolery')
        ->assertRedirect(route('client.tolerycad.create-order', ['chat' => $chat]));
});

it('ouvre la modal d\'achat au lieu de rediriger quand l\'utilisateur n\'a aucun accès', function () {
    fakeOrderFileAccess(false);

    $team = ChatTeam::factory()->create();
    $user = ChatUser::create(['team_id' => $team->id]);
    Auth::setUser($user);

    $chat = Chat::factory()->create([
        'team_id' => $team->id,
        'user_id' => $user->id,
    ]);

    Livewire::test(Chatbot::class, ['chat' => $chat])
        ->call('orderWithTolery')
        ->assertSet('showPurchaseModal', true)
        ->assertNoRedirect()
        // Pas de navigation → le loader du bouton doit être réinitialisé.
        ->assertDispatched('cad-order-blocked');
});

it('câble le bouton « Chiffrer et commander » dans le header (haut à droite)', function () {
    // Le bouton commande vit dans le header, avant l'historique (#2381).
    $header = file_get_contents(
        __DIR__.'/../../resources/views/livewire/partials/chat-header.blade.php'
    );

    expect($header)->toContain('wire:click="orderWithTolery"')
        ->and($header)->toContain('Chiffrer et commander la pièce')
        ->and($header)->toContain('flux:icon.calculator')
        // variant="primary" sans color → couleur accent (= bouton « Nouveau devis »).
        ->and($header)->toContain('variant="primary"')
        ->and($header)->toContain('!rounded-full')
        // Loader persistant jusqu'à la navigation (#2381 UX).
        ->and($header)->toContain('ordering = true')
        ->and($header)->toContain('Préparation du devis');
});

it('garde le bouton « Télécharger » dans le viewer en couleur « Nouveau fichier »', function () {
    $viewer = file_get_contents(
        __DIR__.'/../../resources/views/livewire/partials/viewer-panel.blade.php'
    );

    expect($viewer)->toContain('wire:click="initiateDownload"')
        ->and($viewer)->toContain('Télécharger votre fichier')
        // Violet de marque « Nouveau fichier » + pill.
        ->and($viewer)->toContain('!bg-violettes')
        ->and($viewer)->toContain('!rounded-full')
        // Le bouton commande n'est plus dans le viewer.
        ->and($viewer)->not->toContain('wire:click="orderWithTolery"');
});
