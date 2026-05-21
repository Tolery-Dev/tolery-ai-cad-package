<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Tolery\AiCad\Jobs\SendCompletionEmailIfUnreadJob;
use Tolery\AiCad\Models\Chat;
use Tolery\AiCad\Models\ChatMessage;
use Tolery\AiCad\Models\ChatTeam;
use Tolery\AiCad\Models\ChatUser;
use Tolery\AiCad\Notifications\CadGenerationCompletedNotification;

beforeEach(function () {
    Notification::fake();

    config()->set('ai-cad.notifications.online_threshold_seconds', 30);
    config()->set('ai-cad.chat_user_model', ChatUser::class);

    // Mimic the host app's Laravel `notifications` table (created by
    // `php artisan notifications:table` in real installs).
    if (! Schema::hasTable('notifications')) {
        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });
    }
});

function makeChatMessageForCompletion(?\Carbon\Carbon $lastSeenAt): ChatMessage
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
        'message' => 'done',
    ]);
}

function insertDatabaseNotification(ChatMessage $message, ?\Carbon\Carbon $readAt): void
{
    \Illuminate\Support\Facades\DB::table('notifications')->insert([
        'id' => (string) \Illuminate\Support\Str::uuid(),
        'type' => CadGenerationCompletedNotification::class,
        'notifiable_type' => ChatUser::class,
        'notifiable_id' => $message->user->getKey(),
        'data' => json_encode(['message_id' => $message->id]),
        'read_at' => $readAt,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

test('sends email when notification unread and user offline', function () {
    $message = makeChatMessageForCompletion(lastSeenAt: now()->subMinutes(10));

    // Simulate the unread database notification recorded just before the
    // delayed job fires.
    insertDatabaseNotification($message, readAt: null);

    (new SendCompletionEmailIfUnreadJob($message->id))->handle();

    Notification::assertSentTo(
        $message->user,
        CadGenerationCompletedNotification::class,
    );
});

test('skips email when user is currently online (issue #2199)', function () {
    $message = makeChatMessageForCompletion(lastSeenAt: now()->subSeconds(5));

    insertDatabaseNotification($message, readAt: null);

    (new SendCompletionEmailIfUnreadJob($message->id))->handle();

    Notification::assertNothingSent();
});

test('skips email when notification already read', function () {
    $message = makeChatMessageForCompletion(lastSeenAt: now()->subMinutes(10));

    insertDatabaseNotification($message, readAt: now());

    (new SendCompletionEmailIfUnreadJob($message->id))->handle();

    Notification::assertNothingSent();
});
