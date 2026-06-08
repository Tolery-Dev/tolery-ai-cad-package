<?php

use Illuminate\Support\Facades\Storage;
use Tolery\AiCad\Models\Chat;
use Tolery\AiCad\Models\ChatMessage;
use Tolery\AiCad\Models\ChatTeam;
use Tolery\AiCad\Services\ZipGeneratorService;

beforeEach(function () {
    config(['ai-cad.chat_team_model' => ChatTeam::class]);
    Storage::fake();
});

/**
 * @return array{0: Chat, 1: ChatMessage}
 */
function makeChatWithCadFiles(?string $partName, string $teamName = 'Dupont Industries'): array
{
    $team = ChatTeam::factory()->create(['name' => $teamName]);
    $chat = Chat::factory()->create(['team_id' => $team->id, 'name' => $partName]);

    Storage::put('cad/source.step', 'STEP-CONTENT');
    Storage::put('cad/source.pdf', 'PDF-CONTENT');

    $message = ChatMessage::query()->create([
        'chat_id' => $chat->id,
        'role' => 'assistant',
        'message' => 'done',
        'ai_step_path' => 'cad/source.step',
        'ai_cad_path' => 'cad/source.obj',
        'ai_technical_drawing_path' => 'cad/source.pdf',
    ]);

    return [$chat, $message];
}

/**
 * @return array<int, string>
 */
function zipEntryNames(string $zipPath): array
{
    $zip = new ZipArchive;
    $zip->open($zipPath);

    $names = [];
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $names[] = $zip->getNameIndex($i);
    }
    $zip->close();

    return $names;
}

it('names the chat zip and its files after the part name and version', function () {
    [$chat] = makeChatWithCadFiles('Support Moteur');

    $result = app(ZipGeneratorService::class)->generateChatFilesZip($chat);

    expect($result['success'])->toBeTrue()
        ->and($result['filename'])->toStartWith('support-moteur_v1_')
        ->and($result['filename'])->toEndWith('_tolerycad.zip');

    expect(zipEntryNames($result['path']))
        ->toContain('support-moteur_v1.step')
        ->toContain('support-moteur_v1_plan.pdf');
});

it('names a specific version zip after the part name and version', function () {
    [, $message] = makeChatWithCadFiles('Equerre Inox');

    $result = app(ZipGeneratorService::class)->generateMessageFilesZip($message);

    expect($result['success'])->toBeTrue()
        ->and($result['filename'])->toStartWith('equerre-inox_v1_')
        ->and($result['filename'])->toEndWith('_tolerycad.zip');

    expect(zipEntryNames($result['path']))
        ->toContain('equerre-inox_v1.step')
        ->toContain('equerre-inox_v1_plan.pdf');
});

it('falls back to the team name when the chat has no part name', function () {
    [$chat] = makeChatWithCadFiles(null, teamName: 'Dupont Industries');

    $result = app(ZipGeneratorService::class)->generateChatFilesZip($chat);

    expect($result['success'])->toBeTrue()
        ->and($result['filename'])->toStartWith('dupont-industries_v1_');

    expect(zipEntryNames($result['path']))->toContain('dupont-industries_v1.step');
});
