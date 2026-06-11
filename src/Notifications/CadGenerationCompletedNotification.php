<?php

namespace Tolery\AiCad\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Tolery\AiCad\Models\ChatMessage;

/**
 * Sent when a CAD generation finishes successfully.
 *
 * The channel set is normally `['database']` only — the SendCompletionEmailIfUnreadJob
 * dispatches the same notification with `forceChannels = ['mail']` after a delay
 * if the database notification hasn't been read yet. That ensures the modal's
 * promise ("Vous serez notifié dès que votre pièce sera prête") is always
 * honored, without spamming users who are still in the app and saw the cloche.
 */
class CadGenerationCompletedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array<int, string>|null  $forceChannels  Override the default channel set (e.g. ['mail'])
     */
    public function __construct(
        public ChatMessage $message,
        public ?array $forceChannels = null,
    ) {}

    /** @return array<int, string> */
    public function via(mixed $notifiable): array
    {
        return $this->forceChannels ?? ['database'];
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        $chatUrl = route('client.tolerycad.show-chatbot', ['chat' => $this->message->chat_id]);

        $mail = (new MailMessage)
            ->subject('Votre pièce CAO est prête')
            ->greeting('Bonjour,')
            ->line('La génération de votre pièce ToleryCAD est terminée.');

        // Attach the screenshot preview when available so the user can see their
        // piece directly in the email without having to open the app first.
        $screenshotUrl = $this->message->getScreenshotUrl();
        if ($screenshotUrl) {
            $mail->line('Aperçu de votre pièce :')
                ->line('![]('.$screenshotUrl.')');
        }

        return $mail
            ->action('Télécharger la pièce', $chatUrl)
            ->line('Vous pouvez la télécharger depuis votre chat.');
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
