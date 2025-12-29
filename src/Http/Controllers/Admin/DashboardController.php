<?php

namespace Tolery\AiCad\Http\Controllers\Admin;

use Illuminate\View\View;

class DashboardController
{
    public function index(): View
    {
        return view('ai-cad::admin.dashboard');
    }
}
