<?php

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tolery\AiCad\Jobs\DownloadCadAssetsJob;
use Tolery\AiCad\Models\Chat;
use Tolery\AiCad\Models\ChatMessage;
use Tolery\AiCad\Models\ChatTeam;

/**
 * #2438 — DownloadCadAssetsJob doit refléter fidèlement la disponibilité réelle
 * des assets : ne marquer cad_files_ready qu'une fois TOUS les fichiers persistés,
 * retenter (exception) en cas d'échec transitoire, et signaler un échec définitif
 * via cad_files_failed. Sinon un STEP non téléchargé laisse une URL externe dans
 * ai_step_path → ZipGeneratorService renvoie « Aucun fichier disponible pour ce chat ».
 */
beforeEach(function () {
    config(['ai-cad.chat_team_model' => ChatTeam::class]);
    Storage::fake();
});

const DFM_OBJ_URL = 'https://dfm.test/export/obj';
const DFM_STEP_URL = 'https://dfm.test/export/step';
const DFM_PDF_URL = 'https://dfm.test/export/pdf';

/**
 * Crée un message assistant dont les champs d'assets pointent encore vers les URLs
 * sources DFM (état posé par GenerateCadJob, avant localisation).
 */
function makeMessageAwaitingAssets(): ChatMessage
{
    $team = ChatTeam::factory()->create();
    $chat = Chat::factory()->create(['team_id' => $team->id, 'name' => 'Support Moteur']);

    return ChatMessage::query()->create([
        'chat_id' => $chat->id,
        'role' => ChatMessage::ROLE_ASSISTANT,
        'message' => 'Génération réussie.',
        'ai_cad_path' => DFM_OBJ_URL,
        'ai_step_path' => DFM_STEP_URL,
        'ai_technical_drawing_path' => DFM_PDF_URL,
        'cad_files_ready' => false,
        'cad_files_failed' => false,
    ]);
}

it('localise tous les assets et marque cad_files_ready quand tous les téléchargements réussissent', function () {
    $message = makeMessageAwaitingAssets();

    Http::fake([
        DFM_OBJ_URL => Http::response('OBJ-CONTENT', 200),
        DFM_STEP_URL => Http::response('STEP-CONTENT', 200),
        DFM_PDF_URL => Http::response('PDF-CONTENT', 200),
    ]);

    (new DownloadCadAssetsJob($message->id, [
        'obj' => DFM_OBJ_URL,
        'step' => DFM_STEP_URL,
        'pdf' => DFM_PDF_URL,
    ], 'ai-chat/test/chat-1'))->handle();

    $message->refresh();

    expect($message->cad_files_ready)->toBeTrue()
        ->and($message->cad_files_failed)->toBeFalse()
        // Les champs ne pointent plus vers les URLs externes mais vers le Storage local.
        ->and($message->ai_step_path)->toStartWith('ai-chat/test/chat-1/')
        ->and($message->ai_cad_path)->toStartWith('ai-chat/test/chat-1/')
        ->and($message->ai_technical_drawing_path)->toStartWith('ai-chat/test/chat-1/');

    expect(Storage::exists($message->ai_step_path))->toBeTrue();
    expect(Storage::get($message->ai_step_path))->toBe('STEP-CONTENT');
});

it('lève une exception et ne persiste rien quand un asset échoue (all-or-nothing)', function () {
    $message = makeMessageAwaitingAssets();

    Http::fake([
        DFM_OBJ_URL => Http::response('OBJ-CONTENT', 200),
        DFM_STEP_URL => Http::response('boom', 500), // le STEP échoue
        DFM_PDF_URL => Http::response('PDF-CONTENT', 200),
    ]);

    $job = new DownloadCadAssetsJob($message->id, [
        'obj' => DFM_OBJ_URL,
        'step' => DFM_STEP_URL,
        'pdf' => DFM_PDF_URL,
    ], 'ai-chat/test/chat-1');

    expect(fn () => $job->handle())->toThrow(RuntimeException::class);

    $message->refresh();

    // cad_files_ready reste false et aucun champ n'a été modifié : les URLs sources
    // restent intactes pour un futur retry, et rien n'a été écrit dans le Storage.
    expect($message->cad_files_ready)->toBeFalse()
        ->and($message->ai_step_path)->toBe(DFM_STEP_URL)
        ->and($message->ai_cad_path)->toBe(DFM_OBJ_URL)
        ->and($message->ai_technical_drawing_path)->toBe(DFM_PDF_URL);

    expect(Storage::allFiles('ai-chat/test/chat-1'))->toBeEmpty();
});

it('marque cad_files_failed via failed() après épuisement des tentatives', function () {
    $message = makeMessageAwaitingAssets();

    $job = new DownloadCadAssetsJob($message->id, [
        'step' => DFM_STEP_URL,
    ], 'ai-chat/test/chat-1');

    $job->failed(new RuntimeException('assets non téléchargés'));

    $message->refresh();

    expect($message->cad_files_failed)->toBeTrue()
        ->and($message->cad_files_ready)->toBeFalse();
});

it('marque cad_files_ready immédiatement quand il n\'y a aucun asset à télécharger', function () {
    $message = makeMessageAwaitingAssets();

    (new DownloadCadAssetsJob($message->id, [
        'obj' => '',
        'step' => '',
        'pdf' => '',
    ], 'ai-chat/test/chat-1'))->handle();

    $message->refresh();

    expect($message->cad_files_ready)->toBeTrue()
        ->and($message->cad_files_failed)->toBeFalse();
});
