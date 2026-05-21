<?php

namespace Tolery\AiCad\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Tolery\AiCad\Models\ChatMessage;
use Tolery\AiCad\Support\UserPresence;

class CadGenerationFailedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public ChatMessage $message,
        public string $errorMessage,
    ) {}

    /**
     * Failure notifications go through both the database (cloche) and email
     * channels so a user who closed the tab still hears about it. When the
     * user is actively on the chatbot UI (issue #2199), the cloche alone is
     * enough — we drop the email to avoid inbox spam.
     *
     * @return array<int, string>
     */
    public function via(mixed $notifiable): array
    {
        if (UserPresence::isOnline($notifiable)) {
            return ['database'];
        }

        return ['database', 'mail'];
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        $chatUrl = route('client.tolerycad.show-chatbot', ['chat' => $this->message->chat_id]);

        return (new MailMessage)
            ->subject('La génération de votre pièce a échoué')
            ->greeting('Bonjour,')
            ->line('Une erreur est survenue pendant la génération de votre pièce ToleryCAD.')
            ->line('Vous pouvez réessayer depuis votre chat.')
            ->action('Retourner au chat', $chatUrl)
            ->line('Si le problème persiste, notre équipe est avertie automatiquement.');
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
