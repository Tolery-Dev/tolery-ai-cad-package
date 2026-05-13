<?php

namespace Tolery\AiCad\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Tolery\AiCad\Enum\GenerationStatus;
use Tolery\AiCad\Jobs\GenerateCadJob;
use Tolery\AiCad\Models\Chat;
use Tolery\AiCad\Models\ChatMessage;
use Tolery\AiCad\Models\ChatUser;

/**
 * HTTP entry point for the async CAD generation flow (Phase 2 of issue #152).
 *
 * Replaces the synchronous SSE endpoint exposed by StreamController. The browser
 * no longer holds the SSE stream open for the full generation; instead it POSTs
 * here, gets a message_id back immediately, and listens to the chat.{id} Reverb
 * channel for progress events. Survives reloads / tab closes.
 */
class GenerationController extends Controller
{
    /**
     * Dispatch a new CAD generation for the given chat.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'chat_id' => 'required|integer|exists:chats,id',
            'message' => 'required|string|min:1',
            'session_id' => 'nullable|string',
            'is_edit_request' => 'nullable|boolean',
            'material_choice' => 'nullable|string|in:STEEL,ALUMINUM,STAINLESS',
        ]);

        /** @var Chat $chat */
        $chat = Chat::findOrFail($validated['chat_id']);

        /** @var ChatUser|null $user */
        $user = $request->user();
        if (! $this->userCanAccessChat($user, $chat)) {
            return response()->json(['error' => 'forbidden'], 403);
        }

        // Enforce "one in-flight generation per chat" (the Job is also ShouldBeUnique
        // as a safety net, but this 409 lets the UI show a friendly message).
        $activeGeneration = ChatMessage::query()
            ->where('chat_id', $chat->id)
            ->whereIn('generation_status', [GenerationStatus::PENDING->value, GenerationStatus::RUNNING->value])
            ->exists();

        if ($activeGeneration) {
            return response()->json([
                'error' => 'generation_in_progress',
                'message' => 'Une génération est déjà en cours pour ce chat.',
            ], 409);
        }

        // Persist the user message + create an assistant placeholder with status=pending.
        // The Job will fill in the assistant fields and flip status to completed/failed.
        $userMessage = ChatMessage::create([
            'chat_id' => $chat->id,
            'user_id' => $user->id,
            'role' => ChatMessage::ROLE_USER,
            'message' => $validated['message'],
        ]);

        $assistantMessage = ChatMessage::create([
            'chat_id' => $chat->id,
            'user_id' => $user->id,
            'role' => ChatMessage::ROLE_ASSISTANT,
            'message' => '',
            'generation_status' => GenerationStatus::PENDING,
        ]);

        GenerateCadJob::dispatch(
            messageId: $assistantMessage->id,
            userMessage: $validated['message'],
            sessionId: $validated['session_id'] ?? null,
            isEditRequest: (bool) ($validated['is_edit_request'] ?? false),
            materialChoice: $validated['material_choice'] ?? 'STEEL',
        );

        Log::info('[AICAD] Generation dispatched', [
            'chat_id' => $chat->id,
            'user_message_id' => $userMessage->id,
            'assistant_message_id' => $assistantMessage->id,
            'user_id' => $user->id,
        ]);

        return response()->json([
            'user_message_id' => $userMessage->id,
            'assistant_message_id' => $assistantMessage->id,
            'status' => GenerationStatus::PENDING->value,
        ], 202);
    }

    /**
     * Return the current progress of a generation — used by the chat view to
     * rebuild its state after a reload, before subscribing to the Reverb channel.
     */
    public function progress(Request $request, ChatMessage $message): JsonResponse
    {
        /** @var ChatUser|null $user */
        $user = $request->user();
        if (! $this->userCanAccessChat($user, $message->chat)) {
            return response()->json(['error' => 'forbidden'], 403);
        }

        return response()->json([
            'message_id' => $message->id,
            'chat_id' => $message->chat_id,
            'status' => $message->generation_status?->value,
            'pct' => $message->generation_progress_pct,
            'step' => $message->generation_progress_step,
            'message' => $message->generation_progress_message,
            'started_at' => $message->generation_started_at?->toIso8601String(),
            'completed_at' => $message->generation_completed_at?->toIso8601String(),
            'error' => $message->generation_error,
        ]);
    }

    /**
     * Same access rule as the chat.{chatId} broadcast channel: the user must own
     * the chat or belong to its team. Inlined because ChatPolicy doesn't expose
     * a `view` ability — only admin abilities (viewAsAdmin / downloadFiles / viewAny).
     */
    private function userCanAccessChat(?ChatUser $user, ?Chat $chat): bool
    {
        if (! $user || ! $chat) {
            return false;
        }

        return $chat->user_id === $user->id
            || ($chat->team_id !== null && $chat->team_id === $user->team_id);
    }
}
