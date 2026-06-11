<?php

namespace Tolery\AiCad\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Tolery\AiCad\Models\Chat;
use Tolery\AiCad\Models\ChatMessage;
use Tolery\AiCad\Notifications\CadQuestionPendingNotification;
use Tolery\AiCad\Support\UserPresence;

/**
 * Scans chats where the last message is a user message that has been waiting
 * for an AI reply for longer than `ai-cad.notifications.pending_question_delay_minutes`,
 * and sends a database (cloche) notification — never an email (issue mn-tolery#2352).
 *
 * Two guards prevent notification storms (issue mn-tolery#2352):
 * - only messages newer than `pending_question_max_age_hours` are considered,
 *   so the first run after a deploy can't blast the whole chat history;
 * - de-duplication is keyed on the pending message id and ignores read state,
 *   so a question is notified exactly once — reading the notification never
 *   re-arms it.
 *
 * This job is scheduled by AiCadServiceProvider to run every minute so the
 * effective resolution is ≈ 1 minute.
 *
 * Triggered by: issue mn-tolery#2346 — reduce email noise for background generation.
 */
class SendPendingQuestionEmailJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function handle(): void
    {
        $delayMinutes = (int) config('ai-cad.notifications.pending_question_delay_minutes', 5);

        // Feature disabled when delay is 0.
        if ($delayMinutes <= 0) {
            return;
        }

        $maxAgeHours = (int) config('ai-cad.notifications.pending_question_max_age_hours', 24);

        $threshold = now()->subMinutes($delayMinutes);
        $oldestEligible = now()->subHours($maxAgeHours);

        // For each active chat, get the id of the most recent non-typing message.
        // A chat has a "pending question" when that latest message is a user message
        // created before the threshold — meaning the AI has not replied yet.
        // We use a subquery to find the max id per chat, then join back to get the role.
        $pendingMessages = ChatMessage::query()
            ->whereIn('id', function ($sub) {
                // Latest non-typing message id per chat
                $sub->selectRaw('MAX(id)')
                    ->from('chat_messages')
                    ->where('message', '!=', '[TYPING_INDICATOR]')
                    ->groupBy('chat_id');
            })
            ->where('role', ChatMessage::ROLE_USER)
            ->where('created_at', '<=', $threshold)
            ->where('created_at', '>=', $oldestEligible)
            ->whereHas('chat', function ($q) {
                $q->whereNull('deleted_at');
            })
            ->get(['id', 'chat_id']);

        if ($pendingMessages->isEmpty()) {
            return;
        }

        /** @var \Illuminate\Support\Collection<int, int> $messageIdByChat */
        $messageIdByChat = $pendingMessages->pluck('id', 'chat_id');

        $chats = Chat::whereIn('id', $messageIdByChat->keys())->with('user')->get();

        foreach ($chats as $chat) {
            $this->maybeNotify($chat, (int) $messageIdByChat[$chat->id]);
        }
    }

    protected function maybeNotify(Chat $chat, int $pendingMessageId): void
    {
        $user = $chat->user;

        if (! $user) {
            return;
        }

        // Don't notify users who are actively watching the UI — they can see
        // the chat themselves and a cloche entry would only add noise.
        if (UserPresence::isOnline($user)) {
            Log::info('[AICAD] Pending-question notification skipped — user online', [
                'chat_id' => $chat->id,
                'user_id' => $user->getKey(),
            ]);

            return;
        }

        // De-duplicate on the exact pending message, read or not: each question
        // is notified at most once. A new user message gets a new id, so a new
        // question can still be notified later on the same chat.
        //
        // The standard Laravel notifications table stores `data` as `text` (not
        // jsonb) on PostgreSQL, so whereJsonContains can't use the JSON operator.
        // We match on the serialized JSON string instead — `message_id` is an
        // integer in the payload so the encoded form is stable.
        $alreadySent = DatabaseNotification::query()
            ->where('notifiable_type', $user::class)
            ->where('notifiable_id', $user->getKey())
            ->where('type', CadQuestionPendingNotification::class)
            ->where('data', 'like', '%"message_id":'.$pendingMessageId.'%')
            ->exists();

        if ($alreadySent) {
            Log::info('[AICAD] Pending-question notification already sent for this message, skipping', [
                'chat_id' => $chat->id,
                'message_id' => $pendingMessageId,
            ]);

            return;
        }

        Log::info('[AICAD] Sending pending-question notification', [
            'chat_id' => $chat->id,
            'message_id' => $pendingMessageId,
            'user_id' => $user->getKey(),
        ]);

        Notification::send($user, new CadQuestionPendingNotification($chat, $pendingMessageId));
    }
}
