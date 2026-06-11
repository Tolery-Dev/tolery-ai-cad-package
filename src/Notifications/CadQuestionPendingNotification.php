<?php

namespace Tolery\AiCad\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Tolery\AiCad\Models\Chat;

/**
 * Sent when an AI question has been waiting for a user response for longer than
 * the configured `ai-cad.notifications.pending_question_delay_minutes` threshold.
 *
 * Channels: `database` (in-app bell) + `mail` (reminder email).
 * De-duplication is handled by SendPendingQuestionEmailJob which checks for an
 * existing unread database notification before dispatching.
 */
class CadQuestionPendingNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Chat $chat) {}

    /** @return array<int, string> */
    public function via(mixed $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        $chatUrl = route('client.tolerycad.show-chatbot', ['chat' => $this->chat->id]);

        return (new MailMessage)
            ->subject('ToleryCAD attend votre réponse')
            ->greeting('Bonjour,')
            ->line('Une question est en attente de votre réponse dans ToleryCAD.')
            ->action('Reprendre le chat', $chatUrl)
            ->line('Revenez dans votre chat pour continuer la génération de votre pièce.');
    }

    /** @return array<string, mixed> */
    public function toArray(mixed $notifiable): array
    {
        return [
            'title' => 'ToleryCAD attend votre réponse',
            'body' => 'Une question est en attente de votre réponse dans ToleryCAD.',
            'action_url' => route('client.tolerycad.show-chatbot', ['chat' => $this->chat->id]),
            'chat_id' => $this->chat->id,
        ];
    }
}
