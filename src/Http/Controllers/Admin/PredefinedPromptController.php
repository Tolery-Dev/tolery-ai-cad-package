<?php

namespace Tolery\AiCad\Http\Controllers\Admin;

use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Tolery\AiCad\Models\PredefinedPrompt;

class PredefinedPromptController
{
    public function index(): View
    {
        return view('ai-cad::admin.prompts.index');
    }

    public function create(): View
    {
        return view('ai-cad::admin.prompts.create');
    }

    public function edit(PredefinedPrompt $prompt): View
    {
        return view('ai-cad::admin.prompts.edit', compact('prompt'));
    }

    public function destroy(PredefinedPrompt $prompt): RedirectResponse
    {
        $prompt->delete();

        return redirect()
            ->route('ai-cad.admin.prompts.index')
            ->with('success', 'Prompt supprimé');
    }
}
