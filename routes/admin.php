<?php

use Illuminate\Support\Facades\Route;
use Tolery\AiCad\Http\Controllers\Admin\ChatController;
use Tolery\AiCad\Http\Controllers\Admin\ChatDownloadController;
use Tolery\AiCad\Http\Controllers\Admin\DashboardController;
use Tolery\AiCad\Http\Controllers\Admin\DownloadController;
use Tolery\AiCad\Http\Controllers\Admin\FilePurchaseController;
use Tolery\AiCad\Http\Controllers\Admin\PredefinedPromptController;
use Tolery\AiCad\Http\Controllers\Admin\StepMessageController;

/*
|--------------------------------------------------------------------------
| AI CAD Admin Routes
|--------------------------------------------------------------------------
|
| Admin routes for managing ToleryCAD: conversations, purchases, downloads,
| and predefined prompts.
|
*/

Route::middleware(config('ai-cad.admin.middleware', ['web', 'auth', 'admin']))
    ->prefix(config('ai-cad.admin.prefix', 'admin/tolerycad'))
    ->name('ai-cad.admin.')
    ->group(function () {
        // Dashboard
        Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

        // Chats / Conversations
        Route::prefix('chats')->name('chats.')->group(function () {
            Route::get('/', [ChatController::class, 'index'])->name('index');
            Route::get('/{chat}', [ChatController::class, 'show'])->name('show');
            Route::delete('/{chat}', [ChatController::class, 'destroy'])->name('destroy');
            Route::post('/{chat}/restore', [ChatController::class, 'restore'])
                ->name('restore')
                ->withTrashed();
        });

        // File Purchases
        Route::get('/purchases', [FilePurchaseController::class, 'index'])->name('purchases.index');

        // Downloads
        Route::get('/downloads', [ChatDownloadController::class, 'index'])->name('downloads.index');

        // Secure download routes (with signed URLs)
        Route::get('/download/{chat}', [DownloadController::class, 'download'])
            ->name('download')
            ->middleware('signed');

        // S3 download route (if S3 is configured)
        Route::get('/download-s3/{chat}', [DownloadController::class, 'downloadFromS3'])
            ->name('download.s3')
            ->middleware('signed');

        // Predefined Prompts
        Route::prefix('prompts')->name('prompts.')->group(function () {
            Route::get('/', [PredefinedPromptController::class, 'index'])->name('index');
            Route::get('/create', [PredefinedPromptController::class, 'create'])->name('create');
            Route::get('/{prompt}/edit', [PredefinedPromptController::class, 'edit'])->name('edit');
            Route::delete('/{prompt}', [PredefinedPromptController::class, 'destroy'])->name('destroy');
        });

        // Step Messages
        Route::prefix('step-messages')->name('step-messages.')->group(function () {
            Route::get('/', [StepMessageController::class, 'index'])->name('index');
            Route::get('/create', [StepMessageController::class, 'create'])->name('create');
            Route::get('/{stepMessage}/edit', [StepMessageController::class, 'edit'])->name('edit');
            Route::delete('/{stepMessage}', [StepMessageController::class, 'destroy'])->name('destroy');
        });
    });
