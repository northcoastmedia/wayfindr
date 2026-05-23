<?php

use App\Http\Controllers\AgentConversationController;
use App\Http\Controllers\AgentDashboardController;
use App\Http\Controllers\Auth\SessionController;
use App\Http\Controllers\Widget\WidgetScriptController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/widget.js', WidgetScriptController::class)->name('widget.script');

Route::middleware('guest')->group(function () {
    Route::get('/login', [SessionController::class, 'create'])->name('login');
    Route::post('/login', [SessionController::class, 'store'])->name('login.store');
});

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', AgentDashboardController::class)->name('dashboard');
    Route::get('/dashboard/conversations/{supportCode}', [AgentConversationController::class, 'show'])
        ->name('dashboard.conversations.show');
    Route::post('/dashboard/conversations/{supportCode}/messages', [AgentConversationController::class, 'storeMessage'])
        ->name('dashboard.conversations.messages.store');
    Route::post('/logout', [SessionController::class, 'destroy'])->name('logout');
});
