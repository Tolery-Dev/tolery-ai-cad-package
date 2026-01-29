<?php

namespace Tolery\AiCad\Http\Controllers\Admin;

use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Tolery\AiCad\Models\StepMessage;

class StepMessageController
{
    public function index(): View
    {
        Gate::authorize('viewAny', StepMessage::class);

        return view('ai-cad::admin.step-messages.index');
    }

    public function create(): View
    {
        Gate::authorize('create', StepMessage::class);

        return view('ai-cad::admin.step-messages.create');
    }

    public function edit(StepMessage $stepMessage): View
    {
        Gate::authorize('update', $stepMessage);

        return view('ai-cad::admin.step-messages.edit', compact('stepMessage'));
    }

    public function destroy(StepMessage $stepMessage): RedirectResponse
    {
        Gate::authorize('delete', $stepMessage);

        $stepMessage->delete();

        return redirect()
            ->route('ai-cad.admin.step-messages.index')
            ->with('success', 'Message d\'etape supprime');
    }
}
