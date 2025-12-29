<?php

namespace Tolery\AiCad\Http\Controllers\Admin;

use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Tolery\AiCad\Models\PredefinedPrompt;

class PredefinedPromptController
{
    public function index(): View
    {
        Gate::authorize('viewAny', PredefinedPrompt::class);

        return view('ai-cad::admin.prompts.index');
    }

    public function create(): View
    {
        Gate::authorize('create', PredefinedPrompt::class);

        return view('ai-cad::admin.prompts.create');
    }

    public function edit(PredefinedPrompt $prompt): View
    {
        Gate::authorize('update', $prompt);

        return view('ai-cad::admin.prompts.edit', compact('prompt'));
    }

    public function destroy(PredefinedPrompt $prompt): RedirectResponse
    {
        Gate::authorize('delete', $prompt);

        $prompt->delete();

        return redirect()
            ->route('ai-cad.admin.prompts.index')
            ->with('success', 'Prompt supprimé');
    }
}
