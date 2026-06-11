<?php

namespace Tolery\AiCad\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Tolery\AiCad\Models\Chat;

/**
 * Sent when a chat has been waiting for longer than the configured
 * `ai-cad.notifications.pending_question_delay_minutes` threshold.
 *
 * Database (cloche) only — ToleryCAD no longer sends notification emails
 * (issue mn-tolery#2352). `message_id` identifies the exact pending message
 * so SendPendingQuestionEmailJob never notifies twice for the same question,
 * read or not.
 */
class CadQuestionPendingNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Chat $chat,
        public ?int $pendingMessageId = null,
    ) {}

    /** @return array<int, string> */
    public function via(mixed $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, mixed> */
    public function toArray(mixed $notifiable): array
    {
        return [
            'title' => 'ToleryCAD attend votre réponse',
            'body' => 'Une question est en attente de votre réponse dans ToleryCAD.',
            'action_url' => route('client.tolerycad.show-chatbot', ['chat' => $this->chat->id]),
            'chat_id' => $this->chat->id,
            'message_id' => $this->pendingMessageId,
        ];
    }
}
