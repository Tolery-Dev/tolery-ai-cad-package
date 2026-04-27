<?php

use Tolery\AiCad\Models\Chat;
use Tolery\AiCad\Models\ChatDownload;
use Tolery\AiCad\Models\ChatTeam;

beforeEach(function () {
    config(['ai-cad.chat_team_model' => ChatTeam::class]);
});

describe('ChatTable - statut Téléchargé', function () {
    it('indique qu\'un chat sans téléchargement n\'a pas le statut Téléchargé', function () {
        $team = ChatTeam::factory()->create();
        $chat = Chat::factory()->create(['team_id' => $team->id]);

        $chat->load('downloads');

        expect($chat->downloads->isNotEmpty())->toBeFalse();
    });

    it('indique qu\'un chat avec un téléchargement a le statut Téléchargé', function () {
        $team = ChatTeam::factory()->create();
        $chat = Chat::factory()->create(['team_id' => $team->id]);

        ChatDownload::create([
            'team_id' => $team->id,
            'chat_id' => $chat->id,
            'message_id' => null,
            'downloaded_at' => now(),
        ]);

        $chat->load('downloads');

        expect($chat->downloads->isNotEmpty())->toBeTrue();
    });

    it('indique qu\'un chat avec plusieurs téléchargements a le statut Téléchargé', function () {
        $team = ChatTeam::factory()->create();
        $chat = Chat::factory()->create(['team_id' => $team->id]);

        ChatDownload::create([
            'team_id' => $team->id,
            'chat_id' => $chat->id,
            'message_id' => null,
            'downloaded_at' => now()->subDay(),
        ]);

        ChatDownload::create([
            'team_id' => $team->id,
            'chat_id' => $chat->id,
            'message_id' => null,
            'downloaded_at' => now(),
        ]);

        $chat->load('downloads');

        expect($chat->downloads->isNotEmpty())->toBeTrue()
            ->and($chat->downloads->count())->toBe(2);
    });

    it('ne confond pas les téléchargements d\'un autre chat', function () {
        $team = ChatTeam::factory()->create();
        $chat1 = Chat::factory()->create(['team_id' => $team->id]);
        $chat2 = Chat::factory()->create(['team_id' => $team->id]);

        ChatDownload::create([
            'team_id' => $team->id,
            'chat_id' => $chat2->id,
            'message_id' => null,
            'downloaded_at' => now(),
        ]);

        $chat1->load('downloads');

        expect($chat1->downloads->isNotEmpty())->toBeFalse();
    });
});
