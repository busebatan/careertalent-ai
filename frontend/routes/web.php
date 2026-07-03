<?php

use App\Http\Controllers\App\CareerLadderController;
use App\Http\Controllers\App\ChatController;
use App\Http\Controllers\App\CvBuilderController;
use App\Http\Controllers\App\DashboardController;
use App\Http\Controllers\App\JobMatchesController;
use App\Http\Controllers\App\LearningController;
use App\Http\Controllers\App\LocaleController as PanelLocaleController;
use App\Http\Controllers\App\ProfileController;
use App\Http\Controllers\App\RoadmapController;
use App\Http\Controllers\App\TasksController;
use App\Http\Controllers\Marketing\AuthController;
use App\Http\Controllers\Marketing\HomeController;
use App\Http\Controllers\Marketing\LocaleController as MarketingLocaleController;
use Illuminate\Support\Facades\Route;

// ── Tanıtım sitesi (herkese açık) ──────────────────────────
Route::middleware('marketing.locale')->group(function () {
    Route::controller(HomeController::class)->group(function () {
        Route::get('/', 'index')->name('home');
        Route::get('/ozellikler', 'features')->name('features');
        Route::get('/nasil-calisir', 'howItWorks')->name('how-it-works');
        Route::get('/bootcamp', 'bootcamp')->name('bootcamp');
        Route::get('/meslekler', 'careers')->name('careers');
        Route::get('/fiyatlandirma', 'pricing')->name('pricing');
        Route::get('/galeri', 'gallery')->name('gallery');
        Route::get('/faq', 'faq')->name('faq');
        Route::get('/blog', 'blog')->name('blog');
        Route::get('/hakkimizda', 'about')->name('about');
        Route::get('/iletisim', 'contact')->name('contact');
    });

    Route::get('/giris', [AuthController::class, 'login'])->name('login');
    Route::get('/kayit', [AuthController::class, 'register'])->name('register');
    Route::get('/locale/{locale}', [MarketingLocaleController::class, 'switch'])->name('marketing.locale');
});

// ── Panel (Sprint 1: Breeze kurulunca auth middleware eklenecek) ──
Route::prefix('panel')->name('panel.')->middleware('panel.locale')->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/profil', [ProfileController::class, 'show'])->name('profile');
    Route::get('/kariyer-merdiveni', [CareerLadderController::class, 'show'])->name('career-ladder');
    Route::get('/cv-olustur', [CvBuilderController::class, 'show'])->name('cv-builder');
    Route::get('/yol-haritasi', [RoadmapController::class, 'show'])->name('roadmap');
    Route::get('/egitim-onerileri', [LearningController::class, 'show'])->name('learning');
    Route::get('/ilan-eslestirme', [JobMatchesController::class, 'show'])->name('job-matches');
    Route::post('/ilan-eslestirme/analiz', [JobMatchesController::class, 'analyze'])->name('job-matches.analyze');
    Route::get('/gorevlerim', [TasksController::class, 'show'])->name('tasks');
    Route::get('/sohbet', [ChatController::class, 'show'])->name('chat');
    Route::get('/locale/{locale}', [PanelLocaleController::class, 'switch'])->name('locale');
});
