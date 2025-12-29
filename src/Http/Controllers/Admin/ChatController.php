<?php

namespace Tolery\AiCad\Http\Controllers\Admin;

use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Tolery\AiCad\Models\Chat;

class ChatController
{
    public function index(): View
    {
        return view('ai-cad::admin.chats.index');
    }

    public function show(Chat $chat): View
    {
        $chat->load(['messages.user', 'team']);

        return view('ai-cad::admin.chats.show', compact('chat'));
    }

    public function destroy(Chat $chat): RedirectResponse
    {
        $chat->delete();

        return redirect()
            ->route('ai-cad.admin.chats.index')
            ->with('success', 'Conversation supprimée');
    }

    public function restore(Chat $chat): RedirectResponse
    {
        $chat->restore();

        return redirect()
            ->route('ai-cad.admin.chats.index')
            ->with('success', 'Conversation restaurée');
    }
}
