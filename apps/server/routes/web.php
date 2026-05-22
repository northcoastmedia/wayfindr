<?php

use App\Http\Controllers\AgentConversationController;
use App\Http\Controllers\AgentDashboardController;
use App\Http\Controllers\Auth\SessionController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
        'service' => config('app.name', 'Wayfindr'),
    ]);
});

Route::middleware('guest')->group(function () {
    Route::get('/login', [SessionController::class, 'create'])->name('login');
    Route::post('/login', [SessionController::class, 'store'])->name('login.store');
});

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', AgentDashboardController::class)->name('dashboard');
    Route::get('/dashboard/conversations/{supportCode}', [AgentConversationController::class, 'show'])
        ->name('dashboard.conversations.show');
    Route::post('/logout', [SessionController::class, 'destroy'])->name('logout');
});
