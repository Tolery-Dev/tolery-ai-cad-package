<?php

namespace Tolery\AiCad\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Tolery\AiCad\Models\ChatMessage;

/**
 * Sent when a CAD generation finishes successfully.
 *
 * Database (cloche) only — ToleryCAD no longer sends notification emails
 * (issue mn-tolery#2352): generated files await the user in their open
 * conversations, and unread notifications feed the in-app badges.
 */
class CadGenerationCompletedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public ChatMessage $message) {}

    /** @return array<int, string> */
    public function via(mixed $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, mixed> */
    public function toArray(mixed $notifiable): array
    {
        return [
            'title' => 'Votre pièce CAO est prête',
            'body' => 'La génération de votre pièce ToleryCAD est terminée.',
            'action_url' => route('client.tolerycad.show-chatbot', ['chat' => $this->message->chat_id]),
            'message_id' => $this->message->id,
            'chat_id' => $this->message->chat_id,
        ];
    }
}
