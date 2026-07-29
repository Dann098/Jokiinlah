<?php

use App\Http\Controllers\ConsultationAttachmentDownloadController;
use App\Http\Controllers\Customer\AppointmentController;
use App\Http\Controllers\Customer\DashboardController;
use App\Http\Controllers\Customer\ProfileController;
use App\Http\Controllers\Customer\ProjectController;
use App\Http\Controllers\Customer\ProjectFileController;
use App\Http\Controllers\Customer\ProjectFileDownloadController as CustomerProjectFileDownloadController;
use App\Http\Controllers\Customer\ProjectFileVersionController as CustomerProjectFileVersionController;
use App\Http\Controllers\Customer\ReminderController;
use App\Http\Controllers\Customer\RevisionController;
use App\Http\Controllers\ProjectFileDownloadController;
use App\Http\Controllers\ProjectFileVersionController;
use App\Http\Controllers\Public\ArticleController;
use App\Http\Controllers\Public\ConsultationController;
use App\Http\Controllers\Public\ContactController;
use App\Http\Controllers\Public\FaqController;
use App\Http\Controllers\Public\HomeController;
use App\Http\Controllers\Public\LegalPageController;
use App\Http\Controllers\Public\PortfolioController;
use App\Http\Controllers\Public\ServiceController;
use App\Http\Controllers\Public\SitemapController;
use App\Http\Controllers\RevisionAttachmentDownloadController;
use App\Http\Controllers\TwoFactorSecurityController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');
Route::get('/layanan', [ServiceController::class, 'index'])->name('services.index');
Route::get('/layanan/{service:slug}', [ServiceController::class, 'show'])->name('services.show');
Route::get('/portofolio', [PortfolioController::class, 'index'])->name('portfolios.index');
Route::get('/portofolio/{portfolio:slug}', [PortfolioController::class, 'show'])->name('portfolios.show');
Route::get('/artikel', [ArticleController::class, 'index'])->name('articles.index');
Route::get('/artikel/{article:slug}', [ArticleController::class, 'show'])->name('articles.show');
Route::get('/faq', FaqController::class)->name('faq.index');
Route::get('/kontak', ContactController::class)->name('contact.index');
Route::post('/konsultasi', [ConsultationController::class, 'store'])->middleware('throttle:consultations')->name('consultations.store');
Route::get('/kebijakan-privasi', [LegalPageController::class, 'privacy'])->name('privacy');
Route::get('/syarat-dan-ketentuan', [LegalPageController::class, 'terms'])->name('terms');
Route::get('/sitemap.xml', SitemapController::class)->name('sitemap');
Route::get('/robots.txt', [SitemapController::class, 'robots'])->name('robots');

Route::middleware(['auth', 'active', 'verified'])->group(function (): void {
    Route::get('/keamanan/two-factor', TwoFactorSecurityController::class)
        ->middleware('role:admin,staff')
        ->name('security.two-factor.setup');

    Route::get('/admin/konsultasi/{consultation}/lampiran', ConsultationAttachmentDownloadController::class)
        ->middleware(['role:admin', 'throttle:30,1'])
        ->name('admin.consultations.attachment');
    Route::get('/admin/revisi/{revision}/lampiran', RevisionAttachmentDownloadController::class)
        ->middleware(['role:admin,staff', 'throttle:30,1'])
        ->name('admin.revisions.attachment');

    Route::post('/project-files/{projectFile}/versions', [ProjectFileVersionController::class, 'store'])->middleware('throttle:10,1')->name('project-files.versions.store');
    Route::get('/project-files/{projectFile}/download', ProjectFileDownloadController::class)->middleware('throttle:30,1')->name('project-files.download');

    Route::prefix('dashboard')
        ->name('customer.')
        ->middleware('role:customer')
        ->scopeBindings()
        ->group(function (): void {
            Route::get('/', DashboardController::class)->name('dashboard');
            Route::get('/proyek', [ProjectController::class, 'index'])->name('projects.index');
            Route::get('/proyek/{project}', [ProjectController::class, 'show'])->name('projects.show');

            Route::get('/proyek/{project}/file', [ProjectFileController::class, 'index'])->name('projects.files.index');
            Route::post('/proyek/{project}/file', [ProjectFileController::class, 'store'])
                ->middleware('throttle:customer-mutations')
                ->name('projects.files.store');
            Route::get('/proyek/{project}/file/{projectFile}/download', CustomerProjectFileDownloadController::class)
                ->middleware('throttle:30,1')
                ->name('projects.files.download');
            Route::post('/proyek/{project}/file/{projectFile}/versi', [CustomerProjectFileVersionController::class, 'store'])
                ->middleware('throttle:customer-mutations')
                ->name('projects.files.versions.store');

            Route::get('/proyek/{project}/revisi', [RevisionController::class, 'index'])->name('projects.revisions.index');
            Route::post('/proyek/{project}/revisi', [RevisionController::class, 'store'])
                ->middleware('throttle:customer-mutations')
                ->name('projects.revisions.store');
            Route::get('/proyek/{project}/revisi/{revision}', [RevisionController::class, 'show'])->name('projects.revisions.show');
            Route::get('/proyek/{project}/revisi/{revision}/lampiran', [RevisionAttachmentDownloadController::class, 'customer'])
                ->middleware('throttle:30,1')
                ->name('projects.revisions.attachment');

            Route::get('/pengingat', ReminderController::class)->name('reminders.index');
            Route::get('/jadwal', AppointmentController::class)->name('appointments.index');
            Route::get('/profil', [ProfileController::class, 'edit'])->name('profile.edit');
            Route::patch('/profil', [ProfileController::class, 'update'])
                ->middleware('throttle:customer-mutations')
                ->name('profile.update');
            Route::put('/password', [ProfileController::class, 'updatePassword'])
                ->middleware('throttle:customer-mutations')
                ->name('password.update');
        });

    Route::redirect('/profile', '/dashboard/profil')->middleware('role:customer')->name('profile');

});
