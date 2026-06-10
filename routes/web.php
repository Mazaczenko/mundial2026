<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\BetController;
use App\Http\Controllers\RankingController;
use App\Http\Controllers\StandingsController;
use App\Http\Controllers\TiebreakerController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'show'])->name('login');
    Route::post('/login', [LoginController::class, 'login'])->name('login.post');
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
});
