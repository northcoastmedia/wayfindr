<?php

use App\Http\Controllers\Widget\BootstrapController;
use App\Http\Controllers\Widget\ConversationController;
use App\Http\Controllers\Widget\ConversationMessageController;
use Illuminate\Support\Facades\Route;

Route::post('/widget/bootstrap', BootstrapController::class)->name('widget.bootstrap');
Route::post('/conversations', [ConversationController::class, 'store'])->name('conversations.store');
Route::get('/conversations/{supportCode}/messages', [ConversationMessageController::class, 'index'])
    ->name('conversations.messages.index');
Route::post('/conversations/{supportCode}/messages', [ConversationMessageController::class, 'store'])
    ->name('conversations.messages.store');
