<?php

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
    Route::view('/dashboard', 'dashboard')->middleware('role:customer')->name('dashboard');
    Route::view('/profile', 'profile')->name('profile');
    Route::post('/project-files/{projectFile}/versions', [ProjectFileVersionController::class, 'store'])->middleware('throttle:10,1')->name('project-files.versions.store');
    Route::get('/project-files/{projectFile}/download', ProjectFileDownloadController::class)->middleware('throttle:30,1')->name('project-files.download');

    Route::prefix('admin')->middleware('role:admin,staff')->group(function (): void {
        Route::view('/', 'admin.placeholder')->name('admin.dashboard');
    });
});
