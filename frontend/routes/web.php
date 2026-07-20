<?php

use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\App\CareerLadderController;
use App\Http\Controllers\App\ChatController;
use App\Http\Controllers\App\CvBuilderController;
use App\Http\Controllers\App\CvUploadController;
use App\Http\Controllers\App\DashboardController;
use App\Http\Controllers\App\JobMatchesController;
use App\Http\Controllers\App\LocaleController as PanelLocaleController;
use App\Http\Controllers\App\ProfileController;
use App\Http\Controllers\App\RoadmapController;
use App\Http\Controllers\App\StudentFeaturesController;
use App\Http\Controllers\App\TasksController;
use App\Http\Controllers\Company\CompanyController;
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

    Route::get('/giris', fn () => redirect()->route('login', status: 301));
    Route::get('/kayit', fn () => redirect()->route('register', status: 301));
    Route::post('/giris', [AuthController::class, 'authenticate']);
    Route::post('/kayit', [AuthController::class, 'store']);
    Route::get('/panel/login', [AuthController::class, 'login'])->name('login');
    Route::post('/panel/login', [AuthController::class, 'authenticate'])->name('login.submit');
    Route::get('/panel/register', [AuthController::class, 'register'])->name('register');
    Route::post('/panel/register', [AuthController::class, 'store'])->name('register.submit');
    Route::get('/admin/login', [AuthController::class, 'adminLogin'])->name('admin.login');
    Route::post('/admin/login', [AuthController::class, 'authenticateAdmin'])->name('admin.login.submit');
    Route::get('/company/login', [AuthController::class, 'companyLogin'])->name('company.login');
    Route::post('/company/login', [AuthController::class, 'authenticateCompany'])->name('company.login.submit');
    Route::post('/company/cikis', [AuthController::class, 'logoutCompany'])->name('company.logout');
    Route::get('/company/davet/{token}', [AuthController::class, 'companyInvitation'])->name('company.invitation');
    Route::post('/company/davet/{token}', [AuthController::class, 'acceptCompanyInvitation'])->name('company.invitation.accept');
    Route::post('/cikis', [AuthController::class, 'logout'])->name('logout');
    Route::get('/locale/{locale}', [MarketingLocaleController::class, 'switch'])->name('marketing.locale');
});

Route::get('/company', function () {
    $membership = request()->attributes->get('company.membership');

    return redirect()->route('company.dashboard', [
        'organizationSlug' => $membership['organization_slug'],
    ]);
})->name('company.entry')->middleware(['auth.api', 'auth.api.company', 'panel.locale']);

// ── Admin panel ─────────────────────────────────────────────
Route::prefix('admin')->name('admin.')->middleware(['auth.api', 'auth.api.admin', 'panel.locale'])->group(function () {
    Route::get('/locale/{locale}', [PanelLocaleController::class, 'switch'])->name('locale');
    Route::get('/', [AdminController::class, 'dashboard'])->name('dashboard');
    Route::get('/profil', [AdminController::class, 'profile'])->name('profile');
    Route::patch('/profil', [AdminController::class, 'updateProfile'])->name('profile.update');
    Route::get('/hesaplar', [AdminController::class, 'accounts'])->name('accounts');
    Route::post('/hesaplar', [AdminController::class, 'storeAccount'])->name('accounts.store');
    Route::patch('/hesaplar/{user}', [AdminController::class, 'updateAccount'])->name('accounts.update');
    Route::delete('/hesaplar/{user}', [AdminController::class, 'destroyAccount'])->name('accounts.destroy');
    Route::get('/kurumlar', [AdminController::class, 'organizations'])->name('organizations');
    Route::get('/kurumlar/{organization}', [AdminController::class, 'showOrganization'])->name('organizations.show');
    Route::post('/kurumlar', [AdminController::class, 'storeOrganization'])->name('organizations.store');
    Route::post('/kurumlar/{organization}/sahip-daveti', [AdminController::class, 'inviteOrganizationOwner'])->name('organizations.owner-invite');
    Route::patch('/kurumlar/{organization}', [AdminController::class, 'updateOrganization'])->name('organizations.update');
    Route::delete('/kurumlar/{organization}', [AdminController::class, 'destroyOrganization'])->name('organizations.destroy');
    Route::get('/kariyer-veri-merkezi', [AdminController::class, 'careerData'])->name('career-data');
    Route::post('/kariyer-veri-merkezi/{resource}', [AdminController::class, 'storeCareerData'])->name('career-data.store');
    Route::put('/kariyer-veri-merkezi/{resource}/{record}', [AdminController::class, 'updateCareerData'])->name('career-data.update');
    Route::delete('/kariyer-veri-merkezi/{resource}/{record}', [AdminController::class, 'destroyCareerData'])->name('career-data.destroy');
    Route::get('/ogrenciler', [AdminController::class, 'students'])->name('students');
    Route::get('/ogrenciler/{user}', [AdminController::class, 'showStudent'])->name('students.show');
    Route::post('/ogrenciler', [AdminController::class, 'storeStudent'])->name('students.store');
    Route::patch('/ogrenciler/{user}', [AdminController::class, 'updateStudent'])->name('students.update');
    Route::delete('/ogrenciler/{user}', [AdminController::class, 'destroyStudent'])->name('students.destroy');
    Route::get('/readiness', [AdminController::class, 'readiness'])->name('readiness');
    Route::get('/yetenek-pasaportu', [AdminController::class, 'skillPassport'])->name('skill-passport');
    Route::get('/is-radari', [AdminController::class, 'jobRadar'])->name('job-radar');
    Route::get('/basvurular', [AdminController::class, 'applications'])->name('applications');
    Route::post('/basvurular', [AdminController::class, 'storeApplication'])->name('applications.store');
    Route::patch('/basvurular/{application}', [AdminController::class, 'updateApplication'])->name('applications.update');
    Route::delete('/basvurular/{application}', [AdminController::class, 'destroyApplication'])->name('applications.destroy');
    Route::get('/mulakatlar', [AdminController::class, 'interviews'])->name('interviews');
    Route::post('/mulakatlar', [AdminController::class, 'storeInterview'])->name('interviews.store');
    Route::patch('/mulakatlar/{interview}', [AdminController::class, 'updateInterview'])->name('interviews.update');
    Route::delete('/mulakatlar/{interview}', [AdminController::class, 'destroyInterview'])->name('interviews.destroy');
});

// ── Öğrenci paneli ──────────────────────────────────────────
Route::prefix('panel')->name('panel.')->middleware(['auth.api', 'auth.api.candidate', 'panel.locale'])->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
    Route::redirect('/kariyer-profilim', '/panel/hesap');
    Route::get('/cv-merkezi', [CvBuilderController::class, 'show'])->name('cv-builder');
    Route::post('/cv-merkezi/analiz', [CvUploadController::class, 'analyze'])->name('cv.analyze');
    Route::post('/cv-merkezi/analiz-olusturucu', [CvUploadController::class, 'analyzeBuilder'])->name('cv.analyze-builder');
    Route::get('/cv-merkezi/analiz/{analysisId}', [CvUploadController::class, 'status'])->name('cv.analysis-status');
    Route::post('/cv-merkezi/temizle', [CvUploadController::class, 'clear'])->name('cv.clear');
    Route::post('/cv-merkezi/pdf-arsivle', [CvUploadController::class, 'archiveGeneratedPdf'])->name('cv.archive-generated');
    Route::get('/kariyer-rotam', [RoadmapController::class, 'show'])->name('roadmap');
    Route::post('/kariyer-rotam/hedef', [CareerLadderController::class, 'select'])->name('career-ladder.select');
    Route::get('/kariyer-rotam/plan-durumu/{targetId}', [RoadmapController::class, 'planStatus'])->name('roadmap.plan-status');
    Route::get('/kariyer-rotam/analiz-durumu', [RoadmapController::class, 'analysisStatus'])->name('roadmap.analysis-status');
    Route::get('/ilan-analizi', [JobMatchesController::class, 'show'])->name('job-matches');
    Route::post('/ilan-analizi/analiz', [JobMatchesController::class, 'analyze'])->name('job-matches.analyze');
    Route::get('/ilan-analizi/{jobId}/durum', [JobMatchesController::class, 'status'])->name('job-matches.status');
    Route::post('/ilan-analizi/{jobId}/kaydet', [JobMatchesController::class, 'save'])->name('job-matches.save');
    Route::post('/ilan-analizi/{jobId}/basvurdum', [JobMatchesController::class, 'markApplied'])->name('job-matches.mark-applied');
    Route::post('/ilan-analizi/{jobId}/uygula', [JobMatchesController::class, 'apply'])->name('job-matches.apply');
    Route::delete('/ilan-analizi/{jobId}', [JobMatchesController::class, 'destroy'])->name('job-matches.destroy');
    Route::get('/basvurularim', [StudentFeaturesController::class, 'applications'])->name('applications');
    Route::post('/basvurularim', [StudentFeaturesController::class, 'createApplication'])->name('applications.create');
    Route::patch('/basvurularim/{applicationId}', [StudentFeaturesController::class, 'updateApplication'])->name('applications.update');
    Route::get('/mulakat-hazirligi', [StudentFeaturesController::class, 'interview'])->name('interview');
    Route::post('/mulakat-hazirligi', [StudentFeaturesController::class, 'startInterview'])->name('interview.start');
    Route::post('/mulakat-hazirligi/{interviewId}/cevap', [StudentFeaturesController::class, 'scoreInterview'])->name('interview.score');
    Route::get('/uzmanlardan-destek', [StudentFeaturesController::class, 'mentors'])->name('mentors');
    Route::get('/hesap', [ProfileController::class, 'account'])->name('account');
    Route::post('/hesap/cv-gecmisi/{documentId}/arsivle', [ProfileController::class, 'archiveCurrent'])->name('cv-history.archive-current');
    Route::post('/hesap/cv-gecmisi/{documentId}/analiz', [ProfileController::class, 'analyzeCv'])->name('cv-history.analyze');
    Route::get('/hesap/cv-gecmisi/{documentId}/indir', [ProfileController::class, 'downloadCv'])->name('cv-history.download');
    Route::delete('/hesap/cv-gecmisi/{documentId}', [ProfileController::class, 'destroyCv'])->name('cv-history.destroy');
    Route::put('/hesap/profil', [ProfileController::class, 'update'])->name('account.profile.update');
    Route::get('/ai-yardimcisi', [ChatController::class, 'show'])->name('chat');
    Route::post('/ai-yardimcisi', [ChatController::class, 'send'])->name('chat.send');
    Route::delete('/ai-yardimcisi', [ChatController::class, 'clear'])->name('chat.clear');

    Route::get('/kariyer-rotam/kariyer-merdiveni', fn () => redirect()->to(route('panel.roadmap').'#kariyer-merdiveni'))->name('career-ladder');
    Route::get('/kariyer-rotam/egitimler', fn () => redirect()->to(route('panel.roadmap').'#egitimler'))->name('learning');
    Route::get('/kariyer-rotam/gorevler', [TasksController::class, 'show'])->name('tasks');
    Route::post('/kariyer-rotam/gorevler/kisisel', [TasksController::class, 'createPersonal'])->name('tasks.personal.create');
    Route::patch('/kariyer-rotam/gorevler/kisisel/{taskId}', [TasksController::class, 'updatePersonal'])->name('tasks.personal.update');
    Route::delete('/kariyer-rotam/gorevler/kisisel/{taskId}', [TasksController::class, 'deletePersonal'])->name('tasks.personal.delete');
    Route::patch('/kariyer-rotam/gorevler/{taskId}/not', [TasksController::class, 'updateNote'])->name('tasks.note.update');
    Route::patch('/kariyer-rotam/gorevler/{taskId}/durum', [TasksController::class, 'updateStatus'])->name('tasks.status.update');
    Route::post('/kariyer-rotam/gorevler/{taskId}/evidence', [TasksController::class, 'submitEvidence'])->name('tasks.evidence');
    Route::get('/kariyer-rotam/gorevler/{taskId}', [TasksController::class, 'status'])->name('tasks.status');
    Route::get('/yetenek-pasaportu', [StudentFeaturesController::class, 'skillPassport'])->name('skill-passport');
    Route::post('/yetenek-pasaportu/kanit', [StudentFeaturesController::class, 'submitSkillEvidence'])->name('skill-passport.evidence');
    Route::delete('/yetenek-pasaportu/kanit', [StudentFeaturesController::class, 'clearSkillEvidence'])->name('skill-passport.evidence.clear');
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

Route::prefix('{organizationSlug}')
    ->where(['organizationSlug' => '[a-z0-9]+(?:-[a-z0-9]+)*'])
    ->name('company.')
    ->middleware(['auth.api', 'auth.api.company', 'panel.locale'])
    ->group(function () {
        Route::get('/', [CompanyController::class, 'dashboard'])->name('dashboard');
        Route::get('/locale/{locale}', [PanelLocaleController::class, 'switch'])->name('locale');
        Route::get('/pozisyonlar', [CompanyController::class, 'positions'])->name('positions');
        Route::post('/pozisyonlar', [CompanyController::class, 'createPosition'])->name('positions.create');
        Route::patch('/pozisyonlar/{position}', [CompanyController::class, 'updatePosition'])->name('positions.update');
        Route::delete('/pozisyonlar/{position}', [CompanyController::class, 'deletePosition'])->name('positions.delete');
        Route::get('/pozisyonlar/{position}/adaylar', [CompanyController::class, 'positionApplications'])->name('positions.applications');
        Route::get('/adaylar', [CompanyController::class, 'applications'])->name('applications');
        Route::get('/degerlendirmeler', [CompanyController::class, 'assessments'])->name('assessments');
        Route::get('/profil', [CompanyController::class, 'profile'])->name('profile');
        Route::patch('/profil', [CompanyController::class, 'updateProfile'])->name('profile.update');
        Route::get('/ekip', [CompanyController::class, 'team'])->name('team');
        Route::post('/ekip/davet', [CompanyController::class, 'invite'])->name('team.invite');
        Route::patch('/ekip/{membership}', [CompanyController::class, 'updateMember'])->name('team.update');
        Route::post('/kurum-degistir/{organization}', [CompanyController::class, 'switchOrganization'])->name('organization.switch');
    });
