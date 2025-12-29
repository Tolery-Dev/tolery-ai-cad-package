<?php

namespace Tolery\AiCad\Http\Controllers\Admin;

use Illuminate\View\View;

class ChatDownloadController
{
    public function index(): View
    {
        return view('ai-cad::admin.downloads.index');
    }
}
