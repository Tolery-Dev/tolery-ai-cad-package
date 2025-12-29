<?php

namespace Tolery\AiCad\Http\Controllers\Admin;

use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Tolery\AiCad\Models\Chat;

class ChatController
{
    public function index(): View
    {
        Gate::authorize('viewAny', Chat::class);

        return view('ai-cad::admin.chats.index');
    }

    public function show(Chat $chat): View
    {
        Gate::authorize('viewAsAdmin', $chat);

        $chat->load(['messages.user', 'team']);

        return view('ai-cad::admin.chats.show', compact('chat'));
    }

    public function destroy(Chat $chat): RedirectResponse
    {
        Gate::authorize('viewAsAdmin', $chat);

        $chat->delete();

        return redirect()
            ->route('ai-cad.admin.chats.index')
            ->with('success', 'Conversation supprimée');
    }

    public function restore(Chat $chat): RedirectResponse
    {
        Gate::authorize('viewAsAdmin', $chat);

        $chat->restore();

        return redirect()
            ->route('ai-cad.admin.chats.index')
            ->with('success', 'Conversation restaurée');
    }
}
