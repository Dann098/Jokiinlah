<?php

use App\Http\Controllers\ProjectFileDownloadController;
use App\Http\Controllers\ProjectFileVersionController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::middleware(['auth', 'active', 'verified'])->group(function (): void {
    Route::view('/dashboard', 'dashboard')->middleware('role:customer')->name('dashboard');
    Route::view('/profile', 'profile')->name('profile');

    Route::post('/project-files/{projectFile}/versions', [ProjectFileVersionController::class, 'store'])
        ->middleware('throttle:10,1')
        ->name('project-files.versions.store');
    Route::get('/project-files/{projectFile}/download', ProjectFileDownloadController::class)
        ->middleware('throttle:30,1')
        ->name('project-files.download');

    Route::prefix('admin')->middleware('role:admin,staff')->group(function (): void {
        Route::view('/', 'admin.placeholder')->name('admin.dashboard');
    });
});
