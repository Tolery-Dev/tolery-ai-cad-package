<?php

use Illuminate\Support\Facades\Route;
use Tolery\AiCad\Models\Chat;
use Tolery\AiCad\Models\ChatMessage;
use Tolery\AiCad\Models\ChatTeam;
use Tolery\AiCad\Models\ChatUser;
use Tolery\AiCad\Notifications\CadGenerationCompletedNotification;

beforeEach(function () {
    config()->set('ai-cad.chat_user_model', ChatUser::class);

    // Register the host-app route expected by notifications so tests don't throw
    // RouteNotFoundException (this route lives in mn-tolery, not in the package).
    Route::get('/tolerycad/chat/{chat}', fn () => '')->name('client.tolerycad.show-chatbot');
});

function makeCompletedMessage(?string $screenshotPath = null): ChatMessage
{
    $team = ChatTeam::factory()->create();
    $user = ChatUser::query()->create(['team_id' => $team->id]);
    $chat = Chat::factory()->create(['user_id' => $user->id, 'team_id' => $team->id]);

    return ChatMessage::query()->create([
        'chat_id' => $chat->id,
        'user_id' => $user->id,
        'role' => ChatMessage::ROLE_ASSISTANT,
        'message' => 'Votre pièce est prête.',
        'ai_screenshot_path' => $screenshotPath,
    ]);
}

test('default channel is database only', function () {
    $message = makeCompletedMessage();
    $notification = new CadGenerationCompletedNotification($message);

    expect($notification->via(null))->toBe(['database']);
});

test('forceChannels overrides to mail only', function () {
    $message = makeCompletedMessage();
    $notification = new CadGenerationCompletedNotification($message, ['mail']);

    expect($notification->via(null))->toBe(['mail']);
});

test('toArray contains message_id and chat_id', function () {
    $message = makeCompletedMessage();
    $notification = new CadGenerationCompletedNotification($message);
    $data = $notification->toArray(null);

    expect($data)->toHaveKeys(['message_id', 'chat_id', 'title', 'body', 'action_url']);
    expect($data['message_id'])->toBe($message->id);
    expect($data['chat_id'])->toBe($message->chat_id);
});

test('toMail includes screenshot line when screenshot is available', function () {
    $message = makeCompletedMessage(screenshotPath: 'https://example.com/screenshot.jpg');
    $notification = new CadGenerationCompletedNotification($message, ['mail']);

    $mail = $notification->toMail(null);
    $rendered = implode(' ', array_column($mail->introLines, 'line') + $mail->introLines);

    // The mail should contain a reference to the screenshot URL somewhere in its lines.
    $allLines = $mail->introLines;
    $hasScreenshot = collect($allLines)->contains(fn ($line) => str_contains((string) $line, 'screenshot.jpg'));

    expect($hasScreenshot)->toBeTrue();
});

test('toMail does not include screenshot line when no screenshot', function () {
    $message = makeCompletedMessage(screenshotPath: null);
    $notification = new CadGenerationCompletedNotification($message, ['mail']);

    $mail = $notification->toMail(null);
    $allLines = $mail->introLines;
    $hasScreenshot = collect($allLines)->contains(fn ($line) => str_contains((string) $line, 'screenshot'));

    expect($hasScreenshot)->toBeFalse();
});
