<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\View;
use Livewire\Livewire;
use Tolery\AiCad\Enum\GenerationStatus;
use Tolery\AiCad\Livewire\Chatbot;
use Tolery\AiCad\Models\Chat;
use Tolery\AiCad\Models\ChatMessage;
use Tolery\AiCad\Models\ChatTeam;
use Tolery\AiCad\Models\ChatUser;

/**
 * Régression : au retour sur un chat dont la génération est ENCORE en cours
 * (message assistant '[TYPING_INDICATOR]' en PENDING/RUNNING), mount() rouvrait
 * la modal de progression MAIS affichait aussi à tort le bandeau « Relancer »
 * (hasPendingGeneration). Les deux états doivent être mutuellement exclusifs :
 *  - génération en vol  → reprise temps réel, surtout pas de « Relancer » ;
 *  - vrai orphelin (dernier message = user, aucune génération en vol) → « Relancer ».
 */
beforeEach(function () {
    config(['ai-cad.chat_team_model' => ChatTeam::class]);
    config(['ai-cad.chat_user_model' => ChatUser::class]);
    // Aucun modèle DFM configuré → loadDfmErrorCodes() retourne [] sans toucher la DB.
    config(['ai-cad.dfm_error_code_model' => null]);

    // Vue stub minimale : évite de dépendre des composants Flux Pro et des routes
    // de l'application hôte pour rendre le composant pendant les tests Livewire.
    View::prependNamespace('ai-cad', __DIR__.'/../stubs/ai-cad');
});

/**
 * Crée une team + user authentifié + chat persisté.
 *
 * @return array{0: Chat, 1: ChatTeam, 2: ChatUser}
 */
function makeAuthedChat(): array
{
    $team = ChatTeam::factory()->create(['name' => 'Dupont Industries']);
    $user = ChatUser::create(['team_id' => $team->id]);
    Auth::setUser($user);

    $chat = Chat::factory()->create([
        'team_id' => $team->id,
        'user_id' => $user->id,
        'name' => 'Support Moteur',
    ]);

    return [$chat, $team, $user];
}

it('reprend la génération en cours sans afficher « Relancer »', function () {
    [$chat] = makeAuthedChat();

    // Message user suivi du placeholder assistant encore en vol (RUNNING).
    ChatMessage::query()->create([
        'chat_id' => $chat->id,
        'role' => ChatMessage::ROLE_USER,
        'message' => 'Une équerre 100x50 en acier 2mm',
    ]);
    $assistant = ChatMessage::query()->create([
        'chat_id' => $chat->id,
        'role' => ChatMessage::ROLE_ASSISTANT,
        'message' => '[TYPING_INDICATOR]',
        'generation_status' => GenerationStatus::RUNNING,
    ]);

    Livewire::test(Chatbot::class, ['chat' => $chat])
        ->assertSet('hasPendingGeneration', false)
        ->assertDispatched('aicad-subscribe-progress', messageId: $assistant->id, chatId: $chat->id);
});

it('reprend aussi une génération encore PENDING sans « Relancer »', function () {
    [$chat] = makeAuthedChat();

    ChatMessage::query()->create([
        'chat_id' => $chat->id,
        'role' => ChatMessage::ROLE_USER,
        'message' => 'Un support de moteur',
    ]);
    ChatMessage::query()->create([
        'chat_id' => $chat->id,
        'role' => ChatMessage::ROLE_ASSISTANT,
        'message' => '[TYPING_INDICATOR]',
        'generation_status' => GenerationStatus::PENDING,
    ]);

    Livewire::test(Chatbot::class, ['chat' => $chat])
        ->assertSet('hasPendingGeneration', false)
        ->assertDispatched('aicad-subscribe-progress');
});

it('affiche « Relancer » pour une génération orpheline (aucune génération en vol)', function () {
    [$chat] = makeAuthedChat();

    // Coupure réseau avant le dispatch du job : le message user reste seul, sans
    // placeholder assistant ni statut de génération.
    ChatMessage::query()->create([
        'chat_id' => $chat->id,
        'role' => ChatMessage::ROLE_USER,
        'message' => 'Une bride DN50',
    ]);

    Livewire::test(Chatbot::class, ['chat' => $chat])
        ->assertSet('hasPendingGeneration', true)
        ->assertNotDispatched('aicad-subscribe-progress');
});
