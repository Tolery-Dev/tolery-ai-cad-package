<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\View;
use Livewire\Livewire;
use Tolery\AiCad\Livewire\Chatbot;
use Tolery\AiCad\Models\Chat;
use Tolery\AiCad\Models\ChatDownload;
use Tolery\AiCad\Models\ChatMessage;
use Tolery\AiCad\Models\ChatTeam;
use Tolery\AiCad\Models\ChatUser;
use Tolery\AiCad\Models\FilePurchase;

/**
 * #2374 — Le bouton « Télécharger votre fichier » reste toujours affiché. Au clic :
 *  - assets prêts  → téléchargement immédiat (comportement existant) ;
 *  - assets en cours de préparation (DownloadCadAssetsJob) → modal loader
 *    « Vos fichiers sont en cours de préparation » + polling, puis téléchargement
 *    automatique une fois prêts. Plus de toast d'erreur « Aucun fichier… » dans
 *    ce cas nominal.
 */
beforeEach(function () {
    config(['ai-cad.chat_team_model' => ChatTeam::class]);
    config(['ai-cad.chat_user_model' => ChatUser::class]);

    // Vue stub minimale : évite de dépendre des composants Flux Pro
    // (<flux:composer>…) et des routes de l'application hôte pour rendre le
    // composant pendant les tests Livewire.
    View::prependNamespace('ai-cad', __DIR__.'/../stubs/ai-cad');

    Storage::fake();        // disque par défaut (lecture des assets + ZIP source)
    Storage::fake('public'); // disque public (ZIP publié pour le téléchargement)
});

/**
 * Crée une team + user authentifié + chat avec un message assistant générant
 * une pièce. Le droit de téléchargement est accordé via un FilePurchase
 * (canDownloadChat → reason "purchased"), sans quota à gérer.
 *
 * @param  bool  $filesReady  Positionne cad_files_ready sur le message assistant.
 * @return array{0: Chat, 1: ChatMessage, 2: ChatTeam}
 */
function makeAuthedChatForDownload(bool $filesReady): array
{
    $team = ChatTeam::factory()->create(['name' => 'Dupont Industries']);
    $user = ChatUser::create(['team_id' => $team->id]);
    Auth::setUser($user);

    $chat = Chat::factory()->create([
        'team_id' => $team->id,
        'user_id' => $user->id,
        'name' => 'Support Moteur',
    ]);

    FilePurchase::create([
        'team_id' => $team->id,
        'chat_id' => $chat->id,
        'stripe_payment_intent_id' => 'pi_test_'.uniqid(),
        'amount' => 999,
        'currency' => 'eur',
        'purchased_at' => now(),
    ]);

    Storage::put('cad/source.step', 'STEP-CONTENT');
    Storage::put('cad/source.pdf', 'PDF-CONTENT');

    $message = ChatMessage::query()->create([
        'chat_id' => $chat->id,
        'role' => ChatMessage::ROLE_ASSISTANT,
        'message' => 'Votre pièce est prête.',
        // ai_json_edge_path est posé dès la génération (pilote l'affichage de la
        // pièce et donc du bouton de téléchargement, #2374).
        'ai_json_edge_path' => 'cad/source.json',
        'ai_cad_path' => 'cad/source.obj',
        'ai_step_path' => $filesReady ? 'cad/source.step' : null,
        'ai_technical_drawing_path' => $filesReady ? 'cad/source.pdf' : null,
        'cad_files_ready' => $filesReady,
    ]);

    return [$chat, $message, $team];
}

it('télécharge directement quand les assets sont déjà prêts', function () {
    [$chat] = makeAuthedChatForDownload(filesReady: true);

    Livewire::test(Chatbot::class, ['chat' => $chat])
        ->call('initiateDownload')
        ->assertSet('showPreparingModal', false)
        ->assertSet('pendingFilesDownload', false)
        ->assertDispatched('toast-show');

    // Le ZIP a bien été publié pour le téléchargement et le DL a été comptabilisé.
    expect(Storage::disk('public')->allFiles('downloads'))->not->toBeEmpty();
    expect(ChatDownload::where('chat_id', $chat->id)->exists())->toBeTrue();
});

it('ouvre la modal de préparation sans toast d\'erreur quand le job tourne encore', function () {
    [$chat] = makeAuthedChatForDownload(filesReady: false);

    Livewire::test(Chatbot::class, ['chat' => $chat])
        ->call('initiateDownload')
        ->assertSet('showPreparingModal', true)
        ->assertSet('pendingFilesDownload', true)
        ->assertSet('pendingDownloadMessageId', null)
        ->assertNotDispatched('toast-show'); // pas de « Aucun fichier… »

    // Rien n'est téléchargé ni comptabilisé tant que ce n'est pas prêt.
    expect(Storage::disk('public')->allFiles('downloads'))->toBeEmpty();
    expect(ChatDownload::where('chat_id', $chat->id)->exists())->toBeFalse();
});

it('déclenche automatiquement le téléchargement dès que les assets deviennent prêts', function () {
    [$chat, $message] = makeAuthedChatForDownload(filesReady: false);

    $component = Livewire::test(Chatbot::class, ['chat' => $chat])
        ->call('initiateDownload')
        ->assertSet('showPreparingModal', true)
        ->assertSet('pendingFilesDownload', true);

    // Le polling tourne mais les assets ne sont toujours pas prêts.
    $component->call('checkFilesReady')
        ->assertSet('showPreparingModal', true)
        ->assertSet('pendingFilesDownload', true);

    expect(Storage::disk('public')->allFiles('downloads'))->toBeEmpty();

    // DownloadCadAssetsJob a terminé : assets disponibles + flag à true.
    $message->update([
        'ai_step_path' => 'cad/source.step',
        'ai_technical_drawing_path' => 'cad/source.pdf',
        'cad_files_ready' => true,
    ]);

    // Le prochain tick de polling déclenche le téléchargement et ferme la modal.
    $component->call('checkFilesReady')
        ->assertSet('showPreparingModal', false)
        ->assertSet('pendingFilesDownload', false)
        ->assertSet('pendingDownloadMessageId', null)
        ->assertDispatched('toast-show');

    expect(Storage::disk('public')->allFiles('downloads'))->not->toBeEmpty();
    expect(ChatDownload::where('chat_id', $chat->id)->exists())->toBeTrue();
});

it('diffère aussi le téléchargement d\'une version spécifique non prête', function () {
    [$chat, $message] = makeAuthedChatForDownload(filesReady: false);

    Livewire::test(Chatbot::class, ['chat' => $chat])
        ->call('downloadVersion', $message->id)
        ->assertSet('showPreparingModal', true)
        ->assertSet('pendingFilesDownload', true)
        ->assertSet('pendingDownloadMessageId', $message->id)
        ->assertNotDispatched('toast-show');

    expect(Storage::disk('public')->allFiles('downloads'))->toBeEmpty();
});

it('télécharge une version spécifique directement quand elle est prête', function () {
    [$chat, $message] = makeAuthedChatForDownload(filesReady: true);

    Livewire::test(Chatbot::class, ['chat' => $chat])
        ->call('downloadVersion', $message->id)
        ->assertSet('showPreparingModal', false)
        ->assertSet('pendingFilesDownload', false)
        ->assertDispatched('toast-show');

    expect(Storage::disk('public')->allFiles('downloads'))->not->toBeEmpty();
});

it('permet d\'annuler un téléchargement en attente et coupe le polling', function () {
    [$chat] = makeAuthedChatForDownload(filesReady: false);

    Livewire::test(Chatbot::class, ['chat' => $chat])
        ->call('initiateDownload')
        ->assertSet('showPreparingModal', true)
        ->call('cancelPendingDownload')
        ->assertSet('showPreparingModal', false)
        ->assertSet('pendingFilesDownload', false)
        ->assertSet('pendingDownloadMessageId', null);
});

it('affiche le bouton de téléchargement dès qu\'une pièce est affichée, même sans assets téléchargés', function () {
    // Pièce générée (JSON posé → viewer 3D), mais DownloadCadAssetsJob n'a pas
    // encore écrit l'OBJ/STEP : ai_cad_path null, cad_files_ready false (#2374).
    $team = ChatTeam::factory()->create();
    $user = ChatUser::create(['team_id' => $team->id]);
    Auth::setUser($user);

    $chat = Chat::factory()->create(['team_id' => $team->id, 'user_id' => $user->id]);
    FilePurchase::create([
        'team_id' => $team->id,
        'chat_id' => $chat->id,
        'stripe_payment_intent_id' => 'pi_test_'.uniqid(),
        'amount' => 999,
        'currency' => 'eur',
        'purchased_at' => now(),
    ]);

    ChatMessage::query()->create([
        'chat_id' => $chat->id,
        'role' => ChatMessage::ROLE_ASSISTANT,
        'message' => 'Voici votre pièce.',
        'ai_json_edge_path' => 'cad/source.json', // pièce affichée
        'ai_cad_path' => null,                      // OBJ pas encore téléchargé
        'ai_step_path' => null,
        'cad_files_ready' => false,
    ]);

    // Le bouton est disponible (pièce affichée) et un clic ouvre la modal de
    // préparation au lieu de renvoyer « Aucun fichier… ».
    Livewire::test(Chatbot::class, ['chat' => $chat])
        ->assertSet('showPreparingModal', false)
        ->tap(fn ($c) => expect($c->instance()->hasDownloadablePiece())->toBeTrue())
        ->call('initiateDownload')
        ->assertSet('showPreparingModal', true)
        ->assertSet('pendingFilesDownload', true)
        ->assertNotDispatched('toast-show');

    expect(Storage::disk('public')->allFiles('downloads'))->toBeEmpty();
});

it('lie la modal de préparation à showPreparingModal via wire:model', function () {
    // Garde-fou #2374 : sans wire:model, :open n'est qu'un état initial et Flux
    // n'ouvre pas la modal quand showPreparingModal passe à true côté serveur
    // → la modal ne s'afficherait jamais. Les tests Livewire utilisent une vue
    // stub, on vérifie donc le binding directement dans le partial réel.
    $partial = file_get_contents(
        __DIR__.'/../../resources/views/livewire/partials/preparing-download-modal.blade.php'
    );

    expect($partial)->toContain('wire:model="showPreparingModal"');
});
