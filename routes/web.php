<?php

use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\ResetPasswordController;
use App\Http\Controllers\BetController;
use App\Http\Controllers\PushSubscriptionController;
use App\Http\Controllers\RankingController;
use App\Http\Controllers\SquadController;
use App\Http\Controllers\StandingsController;
use App\Http\Controllers\TiebreakerController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'show'])->name('login');
    Route::post('/login', [LoginController::class, 'login'])->name('login.post');

    Route::get('/forgot-password', [ForgotPasswordController::class, 'show'])->name('password.request');
    Route::post('/forgot-password', [ForgotPasswordController::class, 'store'])->name('password.email');
    Route::get('/reset-password/{token}', [ResetPasswordController::class, 'show'])->name('password.reset');
    Route::post('/reset-password', [ResetPasswordController::class, 'update'])->name('password.update');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [LoginController::class, 'logout'])->name('logout');
    Route::get('/', fn () => redirect()->route('bets.index'));
    Route::get('/bets', [BetController::class, 'index'])->name('bets.index');
    Route::post('/bets', [BetController::class, 'store'])->name('bets.store');
    Route::put('/bets/{bet}', [BetController::class, 'update'])->name('bets.update');
    Route::get('/ranking', [RankingController::class, 'index'])->name('ranking.index');
    Route::get('/standings', [StandingsController::class, 'index'])->name('standings.index');
    Route::get('/tiebreaker', [TiebreakerController::class, 'show'])->name('tiebreaker.show');
    Route::post('/tiebreaker', [TiebreakerController::class, 'store'])->name('tiebreaker.store');
    Route::get('/squads', [SquadController::class, 'index'])->name('squads.index');

    Route::post('/push/subscribe', [PushSubscriptionController::class, 'store'])->name('push.subscribe');
    Route::post('/push/unsubscribe', [PushSubscriptionController::class, 'destroy'])->name('push.unsubscribe');
});
