<?php

use App\Http\Controllers\App\DashboardController;
use App\Http\Controllers\Marketing\HomeController;
use Illuminate\Support\Facades\Route;

// ── Tanıtım sitesi (herkese açık) ──────────────────────────
Route::controller(HomeController::class)->group(function () {
    Route::get('/', 'index')->name('home');
    Route::get('/ozellikler', 'features')->name('features');
    Route::get('/nasil-calisir', 'howItWorks')->name('how-it-works');
    Route::get('/bootcamp', 'bootcamp')->name('bootcamp');
});

// ── Panel (Sprint 1: Breeze kurulunca auth middleware eklenecek) ──
Route::prefix('panel')->name('panel.')->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
});
