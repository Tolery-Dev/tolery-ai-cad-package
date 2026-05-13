<?php

use Illuminate\Support\Facades\Broadcast;
use Tolery\AiCad\Models\Chat;
use Tolery\AiCad\Models\ChatUser;

/*
 * Broadcast channels used by Phase 2 of issue #152 — async CAD generation.
 *
 * The chat.{chatId} channel relays CadGeneration{Started,Progress,Completed,Failed}
 * events from the GenerateCadJob to the user's browser via Reverb.
 */

Broadcast::channel('chat.{chatId}', function (ChatUser $user, int $chatId) {
    $chat = Chat::find($chatId);

    if (! $chat) {
        return false;
    }

    return $chat->user_id === $user->id
        || ($chat->team_id !== null && $chat->team_id === $user->team_id);
});
