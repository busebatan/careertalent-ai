<?php

use App\Http\Controllers\App\CareerLadderController;
use App\Http\Controllers\App\ChatController;
use App\Http\Controllers\App\CvBuilderController;
use App\Http\Controllers\App\CvUploadController;
use App\Http\Controllers\App\DashboardController;
use App\Http\Controllers\App\JobMatchesController;
use App\Http\Controllers\App\LearningController;
use App\Http\Controllers\App\LocaleController as PanelLocaleController;
use App\Http\Controllers\App\ProfileController;
use App\Http\Controllers\App\RoadmapController;
use App\Http\Controllers\App\StudentFeaturesController;
use App\Http\Controllers\App\TasksController;
use App\Http\Controllers\Admin\AdminController;
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
    Route::post('/giris', [AuthController::class, 'authenticate'])->name('login.submit');
    Route::get('/kayit', [AuthController::class, 'register'])->name('register');
    Route::post('/kayit', [AuthController::class, 'store'])->name('register.submit');
    Route::post('/cikis', [AuthController::class, 'logout'])->name('logout');
    Route::get('/locale/{locale}', [MarketingLocaleController::class, 'switch'])->name('marketing.locale');
});


// ── Admin panel ─────────────────────────────────────────────
Route::prefix('admin')->name('admin.')->middleware(['auth.api', 'auth.api.admin'])->group(function () {
    Route::get('/', [AdminController::class, 'dashboard'])->name('dashboard');
    Route::get('/ogrenciler', [AdminController::class, 'students'])->name('students');
    Route::get('/cohortlar', [AdminController::class, 'cohorts'])->name('cohorts');
    Route::get('/readiness', [AdminController::class, 'readiness'])->name('readiness');
    Route::get('/yetenek-pasaportu', [AdminController::class, 'skillPassport'])->name('skill-passport');
    Route::get('/is-radari', [AdminController::class, 'jobRadar'])->name('job-radar');
    Route::get('/basvurular', [AdminController::class, 'applications'])->name('applications');
    Route::get('/mulakatlar', [AdminController::class, 'interviews'])->name('interviews');
    Route::get('/mentorlar', [AdminController::class, 'mentors'])->name('mentors');
    Route::get('/egitimler', [AdminController::class, 'learning'])->name('learning');
    Route::get('/ayarlar', [AdminController::class, 'settings'])->name('settings');
});

// ── Öğrenci paneli ──────────────────────────────────────────
Route::prefix('panel')->name('panel.')->middleware(['auth.api', 'panel.locale'])->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/profil', [ProfileController::class, 'show'])->name('profile');
    Route::post('/cv/analiz', [CvUploadController::class, 'analyze'])->name('cv.analyze');
    Route::post('/cv/analiz-olusturucu', [CvUploadController::class, 'analyzeBuilder'])->name('cv.analyze-builder');
    Route::post('/cv/temizle', [CvUploadController::class, 'clear'])->name('cv.clear');
    Route::get('/kariyer-merdiveni', [CareerLadderController::class, 'show'])->name('career-ladder');
    Route::post('/kariyer-merdiveni/hedef', [CareerLadderController::class, 'select'])->name('career-ladder.select');
    Route::get('/cv-olustur', [CvBuilderController::class, 'show'])->name('cv-builder');
    Route::get('/yol-haritasi', [RoadmapController::class, 'show'])->name('roadmap');
    Route::get('/egitim-onerileri', [LearningController::class, 'show'])->name('learning');
    Route::get('/ilan-eslestirme', [JobMatchesController::class, 'show'])->name('job-matches');
    Route::get('/is-radari', [StudentFeaturesController::class, 'jobRadar'])->name('job-radar');
    Route::get('/basvuru-takibi', [StudentFeaturesController::class, 'applications'])->name('applications');
    Route::get('/yetenek-pasaportu', [StudentFeaturesController::class, 'skillPassport'])->name('skill-passport');
    Route::get('/mulakat-simulasyonu', [StudentFeaturesController::class, 'interview'])->name('interview');
    Route::get('/mentor-degerlendirme', [StudentFeaturesController::class, 'mentors'])->name('mentors');
    Route::post('/ilan-eslestirme/analiz', [JobMatchesController::class, 'analyze'])->name('job-matches.analyze');
    Route::get('/gorevlerim', [TasksController::class, 'show'])->name('tasks');
    Route::get('/sohbet', [ChatController::class, 'show'])->name('chat');
    Route::get('/locale/{locale}', [PanelLocaleController::class, 'switch'])->name('locale');
});
