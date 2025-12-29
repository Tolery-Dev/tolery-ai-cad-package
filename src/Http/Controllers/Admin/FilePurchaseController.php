<?php

namespace Tolery\AiCad\Http\Controllers\Admin;

use Illuminate\View\View;

class FilePurchaseController
{
    public function index(): View
    {
        return view('ai-cad::admin.purchases.index');
    }
}
