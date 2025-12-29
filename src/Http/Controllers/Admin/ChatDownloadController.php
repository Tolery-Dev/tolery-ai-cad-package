<?php

namespace Tolery\AiCad\Http\Controllers\Admin;

use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Tolery\AiCad\Models\ChatDownload;

class ChatDownloadController
{
    public function index(): View
    {
        Gate::authorize('viewAny', ChatDownload::class);

        return view('ai-cad::admin.downloads.index');
    }
}
