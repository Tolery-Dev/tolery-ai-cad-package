<?php

use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tolery\AiCad\Jobs\SendPendingQuestionEmailJob;
use Tolery\AiCad\Models\Chat;
use Tolery\AiCad\Models\ChatMessage;
use Tolery\AiCad\Models\ChatTeam;
use Tolery\AiCad\Models\ChatUser;
use Tolery\AiCad\Notifications\CadQuestionPendingNotification;

beforeEach(function () {
    Notification::fake();

    config()->set('ai-cad.notifications.online_threshold_seconds', 30);
    config()->set('ai-cad.notifications.pending_question_delay_minutes', 5);
    config()->set('ai-cad.notifications.pending_question_max_age_hours', 24);
    config()->set('ai-cad.chat_user_model', ChatUser::class);

    // Register the host-app route expected by notifications (lives in mn-tolery).
    Route::get('/tolerycad/chat/{chat}', fn () => '')->name('client.tolerycad.show-chatbot');

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

/**
 * Create a chat whose last message is a user message sent at the given time.
 */
function makePendingChat(Carbon $lastUserMessageAt, bool $withAssistantReply = false): Chat
{
    $team = ChatTeam::factory()->create();

    $user = ChatUser::query()->create([
        'team_id' => $team->id,
        'last_seen_at' => now()->subHour(), // offline
    ]);

    $chat = Chat::factory()->create([
        'user_id' => $user->id,
        'team_id' => $team->id,
    ]);

    ChatMessage::query()->create([
        'chat_id' => $chat->id,
        'user_id' => $user->id,
        'role' => ChatMessage::ROLE_USER,
        'message' => 'Bonjour, pouvez-vous générer ma pièce ?',
        'created_at' => $lastUserMessageAt,
        'updated_at' => $lastUserMessageAt,
    ]);

    if ($withAssistantReply) {
        ChatMessage::query()->create([
            'chat_id' => $chat->id,
            'user_id' => null,
            'role' => ChatMessage::ROLE_ASSISTANT,
            'message' => 'Voici votre pièce.',
            'created_at' => $lastUserMessageAt->clone()->addSeconds(30),
            'updated_at' => $lastUserMessageAt->clone()->addSeconds(30),
        ]);
    }

    return $chat;
}

function insertPendingQuestionNotification(Chat $chat, ?Carbon $readAt, ?int $messageId = null): void
{
    $messageId ??= (int) ChatMessage::query()->where('chat_id', $chat->id)->max('id');

    DB::table('notifications')->insert([
        'id' => (string) Str::uuid(),
        'type' => CadQuestionPendingNotification::class,
        'notifiable_type' => ChatUser::class,
        'notifiable_id' => $chat->user_id,
        'data' => json_encode(['chat_id' => $chat->id, 'message_id' => $messageId]),
        'read_at' => $readAt,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

test('sends a database-only notification carrying the pending message id', function () {
    $chat = makePendingChat(lastUserMessageAt: now()->subMinutes(10));
    $pendingMessageId = ChatMessage::query()->where('chat_id', $chat->id)->max('id');

    (new SendPendingQuestionEmailJob)->handle();

    Notification::assertSentTo(
        ChatUser::first(),
        CadQuestionPendingNotification::class,
        fn (CadQuestionPendingNotification $notification) => $notification->via(null) === ['database']
            && $notification->pendingMessageId === $pendingMessageId,
    );
});

test('does not send notification when question is too recent', function () {
    makePendingChat(lastUserMessageAt: now()->subMinutes(2)); // below 5-minute threshold

    (new SendPendingQuestionEmailJob)->handle();

    Notification::assertNothingSent();
});

test('does not notify questions older than the max age window', function () {
    // Stale chats from history must never be picked up after a deploy (issue mn-tolery#2352).
    makePendingChat(lastUserMessageAt: now()->subHours(25));

    (new SendPendingQuestionEmailJob)->handle();

    Notification::assertNothingSent();
});

test('does not send when assistant has already replied', function () {
    makePendingChat(lastUserMessageAt: now()->subMinutes(10), withAssistantReply: true);

    (new SendPendingQuestionEmailJob)->handle();

    Notification::assertNothingSent();
});

test('does not send when user is currently online', function () {
    $team = ChatTeam::factory()->create();

    $user = ChatUser::query()->create([
        'team_id' => $team->id,
        'last_seen_at' => now()->subSeconds(5), // online
    ]);

    $chat = Chat::factory()->create(['user_id' => $user->id, 'team_id' => $team->id]);

    ChatMessage::query()->create([
        'chat_id' => $chat->id,
        'user_id' => $user->id,
        'role' => ChatMessage::ROLE_USER,
        'message' => 'Question sans réponse',
        'created_at' => now()->subMinutes(10),
        'updated_at' => now()->subMinutes(10),
    ]);

    (new SendPendingQuestionEmailJob)->handle();

    Notification::assertNothingSent();
});

test('does not resend when an unread notification already exists for the same message', function () {
    $chat = makePendingChat(lastUserMessageAt: now()->subMinutes(10));

    insertPendingQuestionNotification($chat, readAt: null); // already sent, unread

    (new SendPendingQuestionEmailJob)->handle();

    Notification::assertNothingSent();
});

test('does not resend for the same question after the notification is read', function () {
    // Reading the cloche must never re-arm the reminder (issue mn-tolery#2352):
    // each pending question is notified exactly once.
    $chat = makePendingChat(lastUserMessageAt: now()->subMinutes(10));

    insertPendingQuestionNotification($chat, readAt: now()->subMinutes(1)); // read

    (new SendPendingQuestionEmailJob)->handle();

    Notification::assertNothingSent();
});

test('notifies a new pending question even when an older one was already notified', function () {
    $chat = makePendingChat(lastUserMessageAt: now()->subHours(2));
    $firstMessageId = (int) ChatMessage::query()->where('chat_id', $chat->id)->max('id');

    // The first question was notified and read, then answered by the assistant.
    insertPendingQuestionNotification($chat, readAt: now()->subHour(), messageId: $firstMessageId);
    ChatMessage::query()->create([
        'chat_id' => $chat->id,
        'user_id' => null,
        'role' => ChatMessage::ROLE_ASSISTANT,
        'message' => 'Voici votre pièce.',
        'created_at' => now()->subHours(2)->addMinutes(5),
        'updated_at' => now()->subHours(2)->addMinutes(5),
    ]);

    // New user message, unanswered for 10 minutes — a brand new pending question.
    $newMessage = ChatMessage::query()->create([
        'chat_id' => $chat->id,
        'user_id' => $chat->user_id,
        'role' => ChatMessage::ROLE_USER,
        'message' => 'Peux-tu modifier la hauteur ?',
        'created_at' => now()->subMinutes(10),
        'updated_at' => now()->subMinutes(10),
    ]);

    (new SendPendingQuestionEmailJob)->handle();

    Notification::assertSentTo(
        ChatUser::first(),
        CadQuestionPendingNotification::class,
        fn (CadQuestionPendingNotification $notification) => $notification->pendingMessageId === $newMessage->id,
    );
});

test('does nothing when pending_question_delay_minutes is 0 (feature disabled)', function () {
    config()->set('ai-cad.notifications.pending_question_delay_minutes', 0);

    makePendingChat(lastUserMessageAt: now()->subMinutes(10));

    (new SendPendingQuestionEmailJob)->handle();

    Notification::assertNothingSent();
});

test('sends when chat has prior generations but latest user message is unanswered', function () {
    // Simulate an edit scenario: chat already has a completed generation,
    // then the user sends a new message that the AI has not yet answered.
    $team = ChatTeam::factory()->create();
    $user = ChatUser::query()->create([
        'team_id' => $team->id,
        'last_seen_at' => now()->subHour(),
    ]);
    $chat = Chat::factory()->create(['user_id' => $user->id, 'team_id' => $team->id]);

    // Older exchange — this assistant reply must NOT block the pending detection.
    ChatMessage::query()->create([
        'chat_id' => $chat->id,
        'user_id' => $user->id,
        'role' => ChatMessage::ROLE_USER,
        'message' => 'Première demande',
        'created_at' => now()->subHour(),
        'updated_at' => now()->subHour(),
    ]);
    ChatMessage::query()->create([
        'chat_id' => $chat->id,
        'user_id' => null,
        'role' => ChatMessage::ROLE_ASSISTANT,
        'message' => 'Voici la v1 de votre pièce.',
        'created_at' => now()->subHour()->addSeconds(30),
        'updated_at' => now()->subHour()->addSeconds(30),
    ]);

    // New user message with no assistant reply yet — this is the pending question.
    ChatMessage::query()->create([
        'chat_id' => $chat->id,
        'user_id' => $user->id,
        'role' => ChatMessage::ROLE_USER,
        'message' => 'Peux-tu modifier la hauteur ?',
        'created_at' => now()->subMinutes(10),
        'updated_at' => now()->subMinutes(10),
    ]);

    (new SendPendingQuestionEmailJob)->handle();

    Notification::assertSentTo($user, CadQuestionPendingNotification::class);
});
