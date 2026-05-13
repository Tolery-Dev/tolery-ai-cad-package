<?php

namespace Tolery\AiCad\Notifications;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Tolery\AiCad\Models\ChatMessage;

class CadGenerationCompletedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public ChatMessage $message) {}

    /**
     * Email is only sent when the user is unlikely to be looking at the app
     * (last_seen_at older than 30s). Otherwise the Reverb event + the database
     * notification (cloche) is enough — no need to spam the inbox.
     *
     * @return array<int, string>
     */
    public function via(mixed $notifiable): array
    {
        $channels = ['database'];

        if (! $this->isOnline($notifiable)) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    /**
     * Was the user active in the last 30 seconds?
     *
     * Defensive parsing: the consuming app's User model must add the column
     * `last_seen_at` (populated by mn-tolery's TrackUserActivity middleware),
     * but it isn't guaranteed to declare the `datetime` cast — in that case
     * Eloquent hands us a raw string. We accept Carbon, string, or null.
     */
    protected function isOnline(mixed $notifiable): bool
    {
        $raw = $notifiable->last_seen_at ?? null;

        if ($raw === null) {
            return false;
        }

        $lastSeen = $raw instanceof CarbonInterface ? $raw : $this->parseTimestamp($raw);

        return $lastSeen !== null && $lastSeen->greaterThan(now()->subSeconds(30));
    }

    protected function parseTimestamp(mixed $value): ?Carbon
    {
        if (! is_string($value) && ! is_numeric($value)) {
            return null;
        }

        try {
            return Carbon::parse((string) $value);
        } catch (\Throwable) {
            return null;
        }
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        $chatUrl = route('client.tolerycad.show-chatbot', ['chat' => $this->message->chat_id]);

        return (new MailMessage)
            ->subject('Votre pièce CAO est prête')
            ->greeting('Bonjour,')
            ->line('La génération de votre pièce ToleryCAD est terminée.')
            ->action('Voir la pièce', $chatUrl)
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
