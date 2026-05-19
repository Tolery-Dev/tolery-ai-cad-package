<?php

namespace Tolery\AiCad\Http\Controllers\Admin;

use Illuminate\View\View;

class SubscriptionProductController
{
    public function index(): View
    {
        return view('ai-cad::admin.products.index');
    }
}
