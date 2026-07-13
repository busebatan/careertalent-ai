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
    Route::redirect('/kariyer-profilim', '/panel/hesap');
    Route::get('/cv-merkezi', [CvBuilderController::class, 'show'])->name('cv-builder');
    Route::post('/cv-merkezi/analiz', [CvUploadController::class, 'analyze'])->name('cv.analyze');
    Route::post('/cv-merkezi/analiz-olusturucu', [CvUploadController::class, 'analyzeBuilder'])->name('cv.analyze-builder');
    Route::get('/cv-merkezi/analiz/{analysisId}', [CvUploadController::class, 'status'])->name('cv.analysis-status');
    Route::post('/cv-merkezi/temizle', [CvUploadController::class, 'clear'])->name('cv.clear');
    Route::get('/kariyer-rotam', [RoadmapController::class, 'show'])->name('roadmap');
    Route::post('/kariyer-rotam/hedef', [CareerLadderController::class, 'select'])->name('career-ladder.select');
    Route::get('/ilan-analizi', [JobMatchesController::class, 'show'])->name('job-matches');
    Route::post('/ilan-analizi/analiz', [JobMatchesController::class, 'analyze'])->name('job-matches.analyze');
    Route::get('/ilan-analizi/{jobId}/durum', [JobMatchesController::class, 'status'])->name('job-matches.status');
    Route::post('/ilan-analizi/{jobId}/kaydet', [JobMatchesController::class, 'save'])->name('job-matches.save');
    Route::post('/ilan-analizi/{jobId}/uygula', [JobMatchesController::class, 'apply'])->name('job-matches.apply');
    Route::delete('/ilan-analizi/{jobId}', [JobMatchesController::class, 'destroy'])->name('job-matches.destroy');
    Route::get('/basvurularim', [StudentFeaturesController::class, 'applications'])->name('applications');
    Route::get('/mulakat-hazirligi', [StudentFeaturesController::class, 'interview'])->name('interview');
    Route::get('/uzmanlardan-destek', [StudentFeaturesController::class, 'mentors'])->name('mentors');
    Route::get('/hesap', [ProfileController::class, 'account'])->name('account');
    Route::get('/ai-yardimcisi', [ChatController::class, 'show'])->name('chat');

    Route::get('/kariyer-rotam/kariyer-merdiveni', fn () => redirect()->to(route('panel.roadmap').'#kariyer-merdiveni'))->name('career-ladder');
    Route::get('/kariyer-rotam/egitimler', fn () => redirect()->to(route('panel.roadmap').'#egitimler'))->name('learning');
    Route::get('/kariyer-rotam/gorevler', fn () => redirect()->to(route('panel.roadmap').'#gorevler'))->name('tasks');
    Route::post('/kariyer-rotam/gorevler/{taskId}/evidence', [TasksController::class, 'submitEvidence'])->name('tasks.evidence');
    Route::get('/kariyer-rotam/gorevler/{taskId}', [TasksController::class, 'status'])->name('tasks.status');
    Route::get('/yetenek-pasaportu', [StudentFeaturesController::class, 'skillPassport'])->name('skill-passport');
    Route::redirect('/kariyer-profilim/yetenekler', '/panel/yetenek-pasaportu');
    Route::get('/ilan-analizi/radar', fn () => redirect()->route('panel.job-matches'))->name('job-radar');
    Route::redirect('/kariyer-rotam/mentor', '/panel/uzmanlardan-destek');

    Route::redirect('/profil', '/panel/hesap');
    Route::redirect('/cv-olustur', '/panel/cv-merkezi');
    Route::redirect('/kariyer-merdiveni', '/panel/kariyer-rotam/kariyer-merdiveni');
    Route::redirect('/yol-haritasi', '/panel/kariyer-rotam');
    Route::redirect('/egitim-onerileri', '/panel/kariyer-rotam/egitimler');
    Route::redirect('/ilan-eslestirme', '/panel/ilan-analizi');
    Route::redirect('/is-radari', '/panel/ilan-analizi/radar');
    Route::redirect('/basvuru-takibi', '/panel/basvurularim');
    Route::redirect('/mulakat-simulasyonu', '/panel/mulakat-hazirligi');
    Route::redirect('/mentor-degerlendirme', '/panel/uzmanlardan-destek');
    Route::redirect('/gorevlerim', '/panel/kariyer-rotam/gorevler');
    Route::redirect('/sohbet', '/panel/ai-yardimcisi');
    Route::get('/locale/{locale}', [PanelLocaleController::class, 'switch'])->name('locale');
});
