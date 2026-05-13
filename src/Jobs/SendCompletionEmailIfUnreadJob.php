<?php

namespace Tolery\AiCad\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Tolery\AiCad\Models\ChatMessage;
use Tolery\AiCad\Notifications\CadGenerationCompletedNotification;

/**
 * Dispatched (with a delay) immediately after the database completion notification
 * is sent. If the user hasn't read the cloche notification by the time this job
 * runs, we send an email so the modal's "Vous serez notifié" promise is honored
 * even when the user closed the tab right after seeing it.
 *
 * If the notification has already been read, this job is a no-op — no email spam
 * for users who saw the cloche in time.
 */
class SendCompletionEmailIfUnreadJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function __construct(public int $messageId) {}

    public function handle(): void
    {
        $message = ChatMessage::find($this->messageId);

        if (! $message) {
            return;
        }

        $user = $message->user ?? $message->chat?->user;

        if (! $user) {
            return;
        }

        // The standard Laravel notifications table stores `data` as `text` (not
        // jsonb) on PostgreSQL, so whereJsonContains can't use the JSON operator.
        // We match on the serialized JSON string instead — `message_id` is an
        // integer in the payload so the encoded form is stable.
        $unread = DatabaseNotification::query()
            ->where('notifiable_type', $user::class)
            ->where('notifiable_id', $user->getKey())
            ->where('type', CadGenerationCompletedNotification::class)
            ->where('data', 'like', '%"message_id":'.$message->id.'%')
            ->whereNull('read_at')
            ->exists();

        if (! $unread) {
            Log::info('[AICAD] Completion notification already read, skipping email', [
                'message_id' => $message->id,
            ]);

            return;
        }

        Log::info('[AICAD] Completion notification unread after delay, sending email', [
            'message_id' => $message->id,
            'user_id' => $user->getKey(),
        ]);

        Notification::send($user, new CadGenerationCompletedNotification($message, ['mail']));
    }
}
