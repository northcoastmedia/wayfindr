<?php

use App\Http\Controllers\AgentConversationController;
use App\Http\Controllers\AgentDashboardController;
use App\Http\Controllers\AgentSiteController;
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
    Route::get('/dashboard/sites/new', [AgentSiteController::class, 'create'])
        ->name('dashboard.sites.create');
    Route::post('/dashboard/sites', [AgentSiteController::class, 'store'])
        ->name('dashboard.sites.store');
    Route::get('/dashboard/sites/{site}', [AgentSiteController::class, 'show'])
        ->name('dashboard.sites.show');
    Route::put('/dashboard/sites/{site}', [AgentSiteController::class, 'update'])
        ->name('dashboard.sites.update');
    Route::get('/dashboard/conversations/{supportCode}', [AgentConversationController::class, 'show'])
        ->name('dashboard.conversations.show');
    Route::post('/dashboard/conversations/{supportCode}/close', [AgentConversationController::class, 'close'])
        ->name('dashboard.conversations.close');
    Route::post('/dashboard/conversations/{supportCode}/reopen', [AgentConversationController::class, 'reopen'])
        ->name('dashboard.conversations.reopen');
    Route::post('/dashboard/conversations/{supportCode}/messages', [AgentConversationController::class, 'storeMessage'])
        ->name('dashboard.conversations.messages.store');
    Route::post('/dashboard/conversations/{supportCode}/tickets', [AgentConversationController::class, 'storeTicket'])
        ->name('dashboard.conversations.tickets.store');
    Route::post('/dashboard/conversations/{supportCode}/cobrowse/request', [AgentConversationController::class, 'requestCobrowse'])
        ->name('dashboard.conversations.cobrowse.request');
    Route::post('/dashboard/conversations/{supportCode}/cobrowse/end', [AgentConversationController::class, 'endCobrowse'])
        ->name('dashboard.conversations.cobrowse.end');
    Route::post('/logout', [SessionController::class, 'destroy'])->name('logout');
});
