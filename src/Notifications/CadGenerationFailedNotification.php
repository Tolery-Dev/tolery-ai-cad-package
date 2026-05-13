<?php

namespace Tolery\AiCad\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Tolery\AiCad\Models\ChatMessage;

class CadGenerationFailedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public ChatMessage $message,
        public string $errorMessage,
    ) {}

    /**
     * Failure notifications are always sent by both database and email — the user
     * needs to know without having to come back to the app.
     *
     * @return array<int, string>
     */
    public function via(mixed $notifiable): array
    {
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
