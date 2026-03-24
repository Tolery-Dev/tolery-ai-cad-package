<?php

use Illuminate\Support\Facades\Route;
use Tolery\AiCad\Http\Controllers\CadFileController;
use Tolery\AiCad\Http\Controllers\StreamController;
use Tolery\AiCad\Http\Controllers\StripeWebhookController;

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

    // Sert les fichiers JSON CAO depuis le Storage (évite CORS et problèmes d'URL Storage)
    Route::get('/file/{messageId}/json', [CadFileController::class, 'serveJson'])
        ->name('file.json');
});

/*
|--------------------------------------------------------------------------
| AI-CAD Stripe Webhooks (separate from main app Cashier webhooks)
|--------------------------------------------------------------------------
|
| This webhook endpoint uses its own Stripe account (AICAD_STRIPE_*)
| and validates signatures independently from Laravel Cashier.
|
*/

Route::post('/ai-cad/stripe/webhook', [StripeWebhookController::class, 'handleWebhook'])
    ->name('ai-cad.stripe.webhook');
