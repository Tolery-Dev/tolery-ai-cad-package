<?php

use Illuminate\Support\Facades\Route;
use Tolery\AiCad\Http\Controllers\StreamController;

/*
|--------------------------------------------------------------------------
| AI CAD Package Routes
|--------------------------------------------------------------------------
|
| Routes for AI CAD package features, including streaming SSE endpoint.
|
*/

Route::middleware(['web', 'auth'])->prefix('ai-cad')->name('ai-cad.')->group(function () {
    // Streaming SSE endpoint - proxies the external API to avoid CORS and secure the token
    Route::post('/stream/generate-cad', [StreamController::class, 'generateCadStream'])
        ->name('stream.generate-cad');
});
