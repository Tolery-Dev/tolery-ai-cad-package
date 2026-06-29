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

/**
 * #2381 — Bouton bleu « Chiffrer et commander la pièce » : envoie la pièce
 * générée dans un devis Tolery. Le chiffrage/commande vit dans l'app hôte ;
 * le composant ne fait que rediriger vers sa route.
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

it('redirige vers la route de chiffrage hôte au clic sur « Chiffrer et commander »', function () {
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

it('expose le bouton bleu « Chiffrer et commander » dans le viewer', function () {
    // La vue réelle (les tests Livewire utilisent une vue stub) : on garantit le
    // câblage du bouton bleu directement dans le partial.
    $partial = file_get_contents(
        __DIR__.'/../../resources/views/livewire/partials/viewer-panel.blade.php'
    );

    expect($partial)->toContain('wire:click="orderWithTolery"')
        ->and($partial)->toContain('Chiffrer et commander la pièce')
        ->and($partial)->toContain('color="blue"');
});
