<?php

namespace Tolery\AiCad\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Tolery\AiCad\Models\ChatMessage;

/**
 * Sent when a CAD generation fails.
 *
 * Database (cloche) only — ToleryCAD no longer sends notification emails
 * (issue mn-tolery#2352): the user can retry from their conversation, and
 * unread notifications feed the in-app badges.
 */
class CadGenerationFailedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public ChatMessage $message,
        public string $errorMessage,
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
            'title' => 'Génération de pièce CAO interrompue',
            'body' => 'Une erreur est survenue pendant la génération. Vous pouvez réessayer depuis votre chat.',
            'action_url' => route('client.tolerycad.show-chatbot', ['chat' => $this->message->chat_id]),
            'message_id' => $this->message->id,
            'chat_id' => $this->message->chat_id,
        ];
    }
}
