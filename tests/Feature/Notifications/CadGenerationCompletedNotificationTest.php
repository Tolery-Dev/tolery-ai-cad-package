<?php

use Illuminate\Support\Facades\Route;
use Tolery\AiCad\Models\Chat;
use Tolery\AiCad\Models\ChatMessage;
use Tolery\AiCad\Models\ChatTeam;
use Tolery\AiCad\Models\ChatUser;
use Tolery\AiCad\Notifications\CadGenerationCompletedNotification;
use Tolery\AiCad\Notifications\CadGenerationFailedNotification;
use Tolery\AiCad\Notifications\CadQuestionPendingNotification;

beforeEach(function () {
    config()->set('ai-cad.chat_user_model', ChatUser::class);

    // Register the host-app route expected by notifications so tests don't throw
    // RouteNotFoundException (this route lives in mn-tolery, not in the package).
    Route::get('/tolerycad/chat/{chat}', fn () => '')->name('client.tolerycad.show-chatbot');
});

function makeCompletedMessage(): ChatMessage
{
    $team = ChatTeam::factory()->create();
    $user = ChatUser::query()->create(['team_id' => $team->id]);
    $chat = Chat::factory()->create(['user_id' => $user->id, 'team_id' => $team->id]);

    return ChatMessage::query()->create([
        'chat_id' => $chat->id,
        'user_id' => $user->id,
        'role' => ChatMessage::ROLE_ASSISTANT,
        'message' => 'Votre pièce est prête.',
    ]);
}

test('completed notification is database only (no email, issue mn-tolery#2352)', function () {
    $message = makeCompletedMessage();
    $notification = new CadGenerationCompletedNotification($message);

    expect($notification->via(null))->toBe(['database']);
});

test('failed notification is database only (no email, issue mn-tolery#2352)', function () {
    $message = makeCompletedMessage();
    $notification = new CadGenerationFailedNotification($message, 'boom');

    expect($notification->via(null))->toBe(['database']);
});

test('pending-question notification is database only (no email, issue mn-tolery#2352)', function () {
    $message = makeCompletedMessage();
    $notification = new CadQuestionPendingNotification($message->chat, $message->id);

    expect($notification->via(null))->toBe(['database']);
});

test('toArray contains message_id and chat_id', function () {
    $message = makeCompletedMessage();
    $notification = new CadGenerationCompletedNotification($message);
    $data = $notification->toArray(null);

    expect($data)->toHaveKeys(['message_id', 'chat_id', 'title', 'body', 'action_url']);
    expect($data['message_id'])->toBe($message->id);
    expect($data['chat_id'])->toBe($message->chat_id);
});

test('pending-question toArray contains chat_id and pending message_id', function () {
    $message = makeCompletedMessage();
    $notification = new CadQuestionPendingNotification($message->chat, $message->id);
    $data = $notification->toArray(null);

    expect($data)->toHaveKeys(['message_id', 'chat_id', 'title', 'body', 'action_url']);
    expect($data['message_id'])->toBe($message->id);
    expect($data['chat_id'])->toBe($message->chat_id);
});
