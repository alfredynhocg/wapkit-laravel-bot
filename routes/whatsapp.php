<?php

use Wapkit\LaravelBot\Http\Controllers\WhatsAppWebhookController;
use Illuminate\Support\Facades\Route;

Route::prefix(config('whatsapp-bot.routes.prefix', 'webhook'))
    ->middleware(config('whatsapp-bot.routes.middleware', []))
    ->group(function () {
        Route::get('/whatsapp', [WhatsAppWebhookController::class, 'verify'])->name('whatsapp.verify');
        Route::post('/whatsapp', [WhatsAppWebhookController::class, 'receive'])->name('whatsapp.receive');
    });
