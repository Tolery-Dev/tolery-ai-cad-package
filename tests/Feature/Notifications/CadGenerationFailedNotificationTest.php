<?php

use Carbon\Carbon;
use Tolery\AiCad\Models\Chat;
use Tolery\AiCad\Models\ChatMessage;
use Tolery\AiCad\Models\ChatTeam;
use Tolery\AiCad\Models\ChatUser;
use Tolery\AiCad\Notifications\CadGenerationFailedNotification;

beforeEach(function () {
    config()->set('ai-cad.notifications.online_threshold_seconds', 30);
    config()->set('ai-cad.chat_user_model', ChatUser::class);
});

function makeChatMessageForFailure(?Carbon $lastSeenAt): ChatMessage
{
    $team = ChatTeam::factory()->create();

    $user = ChatUser::query()->create([
        'team_id' => $team->id,
        'last_seen_at' => $lastSeenAt,
    ]);

    $chat = Chat::factory()->create([
        'user_id' => $user->id,
        'team_id' => $team->id,
    ]);

    return ChatMessage::query()->create([
        'chat_id' => $chat->id,
        'user_id' => $user->id,
        'role' => 'assistant',
        'message' => 'failed',
    ]);
}

// ToleryCAD never emails users (issue mn-tolery#2352): the failure notification
// is database (cloche) only, whatever the user's presence state.
test('failure notification is database only for offline users', function () {
    $message = makeChatMessageForFailure(lastSeenAt: now()->subMinutes(10));

    $notification = new CadGenerationFailedNotification($message, 'boom');

    expect($notification->via($message->user))->toBe(['database']);
});

test('failure notification is database only for users currently online', function () {
    $message = makeChatMessageForFailure(lastSeenAt: now()->subSeconds(5));

    $notification = new CadGenerationFailedNotification($message, 'boom');

    expect($notification->via($message->user))->toBe(['database']);
});

test('failure notification is database only when user has never been seen', function () {
    $message = makeChatMessageForFailure(lastSeenAt: null);

    $notification = new CadGenerationFailedNotification($message, 'boom');

    expect($notification->via($message->user))->toBe(['database']);
});
