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
 * Scans all chats where the last message is a user message that has been waiting
 * for an AI reply for longer than `ai-cad.notifications.pending_question_delay_minutes`.
 *
 * For each such chat, exactly ONE reminder notification is sent per unanswered
 * question — de-duplication is enforced by checking for an existing unread
 * database notification with the same `chat_id`.
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

        $threshold = now()->subMinutes($delayMinutes);

        // For each active chat, get the id of the most recent non-typing message.
        // A chat has a "pending question" when that latest message is a user message
        // created before the threshold — meaning the AI has not replied yet.
        // We use a subquery to find the max id per chat, then join back to get the role.
        $pendingChatIds = ChatMessage::query()
            ->whereIn('id', function ($sub) {
                // Latest non-typing message id per chat
                $sub->selectRaw('MAX(id)')
                    ->from('chat_messages')
                    ->where('message', '!=', '[TYPING_INDICATOR]')
                    ->groupBy('chat_id');
            })
            ->where('role', ChatMessage::ROLE_USER)
            ->where('created_at', '<=', $threshold)
            ->whereHas('chat', function ($q) {
                $q->whereNull('deleted_at');
            })
            ->pluck('chat_id')
            ->unique();

        if ($pendingChatIds->isEmpty()) {
            return;
        }

        $chats = Chat::whereIn('id', $pendingChatIds)->with('user')->get();

        foreach ($chats as $chat) {
            $this->maybeNotify($chat);
        }
    }

    protected function maybeNotify(Chat $chat): void
    {
        $user = $chat->user;

        if (! $user) {
            return;
        }

        // Don't email users who are actively watching the UI — they can see the
        // chat themselves and an email would only add noise.
        if (UserPresence::isOnline($user)) {
            Log::info('[AICAD] Pending-question email skipped — user online', [
                'chat_id' => $chat->id,
                'user_id' => $user->getKey(),
            ]);

            return;
        }

        // De-duplicate: only send if there is no existing unread pending-question
        // notification for this chat. This prevents repeat emails if the scheduler
        // fires multiple times while the question is still unanswered.
        $alreadySent = DatabaseNotification::query()
            ->where('notifiable_type', $user::class)
            ->where('notifiable_id', $user->getKey())
            ->where('type', CadQuestionPendingNotification::class)
            ->where('data', 'like', '%"chat_id":'.$chat->id.'%')
            ->whereNull('read_at')
            ->exists();

        if ($alreadySent) {
            Log::info('[AICAD] Pending-question notification already sent and unread, skipping', [
                'chat_id' => $chat->id,
            ]);

            return;
        }

        Log::info('[AICAD] Sending pending-question notification', [
            'chat_id' => $chat->id,
            'user_id' => $user->getKey(),
        ]);

        Notification::send($user, new CadQuestionPendingNotification($chat));
    }
}
