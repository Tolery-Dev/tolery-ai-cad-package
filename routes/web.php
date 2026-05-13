<?php

use Illuminate\Support\Facades\Route;
use Tolery\AiCad\Http\Controllers\CadFileController;
use Tolery\AiCad\Http\Controllers\GenerationController;
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
    // Streaming SSE endpoint - proxies the external API to avoid CORS and secure the token.
    // Kept for backward compatibility while the frontend migrates to the Reverb-based flow
    // (issue #152 Phase 2 — see GenerationController below).
    Route::post('/stream/generate-cad', [StreamController::class, 'generateCadStream'])
        ->name('stream.generate-cad');

    // Async CAD generation (Phase 2 of #152): dispatches GenerateCadJob and returns 202.
    // Progress is then delivered via Reverb on PrivateChannel('chat.{id}'); the GET endpoint
    // below lets the client rebuild its state after a reload before subscribing.
    Route::post('/generations', [GenerationController::class, 'store'])
        ->name('generations.store');
    Route::get('/messages/{message}/progress', [GenerationController::class, 'progress'])
        ->name('messages.progress');

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
