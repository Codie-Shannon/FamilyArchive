<?php

use App\Http\Controllers\Admin\ArchivePromotionController;
use App\Http\Controllers\Admin\ArchiveSchemaController;
use App\Http\Controllers\Admin\ArchiveStorageController;
use App\Http\Controllers\Admin\DuplicateCandidateController;
use App\Http\Controllers\Admin\PhotoIntakeController;
use App\Http\Controllers\Admin\ViewingDerivativeController;
use App\Http\Controllers\Archive\ArchiveBrowseController;
use App\Http\Controllers\Archive\PrivateDerivativeController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');
Route::match(['get', 'post'], '/register', function (): never {
    abort(404);
})->name('register');
Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::view('/dashboard', 'dashboard')->name('dashboard');
    Route::middleware('owner')->group(function (): void {
        Route::get('/archive', [ArchiveBrowseController::class, 'index'])->name('archive.index');
        Route::get('/archive/photos/{mediaItem}', [ArchiveBrowseController::class, 'show'])->name('archive.photos.show');
        Route::get('/archive/derivatives/{mediaFileVersion}/preview', PrivateDerivativeController::class)->name('archive.derivatives.preview');
    });
    Route::view('/admin', 'admin.dashboard')->middleware('owner')->name('admin.dashboard');
    Route::get('/admin/archive-schema', ArchiveSchemaController::class)->middleware('owner')->name('admin.archive-schema');
    Route::get('/admin/archive-storage', ArchiveStorageController::class)->middleware('owner')->name('admin.archive-storage');
    Route::middleware('owner')->prefix('admin')->name('admin.')->group(function (): void {
        Route::get('/photo-intake', [PhotoIntakeController::class, 'index'])->name('photo-intake.index');
        Route::post('/photo-intake', [PhotoIntakeController::class, 'store'])->name('photo-intake.store');
        Route::get('/incoming-uploads', [PhotoIntakeController::class, 'queue'])->name('photo-intake.queue');
        Route::get('/incoming-uploads/{incomingUpload}', [PhotoIntakeController::class, 'show'])->name('photo-intake.show');
        Route::get('/duplicate-candidates', [DuplicateCandidateController::class, 'index'])->name('duplicate-candidates.index');
        Route::get('/duplicate-candidates/{candidate}', [DuplicateCandidateController::class, 'show'])->name('duplicate-candidates.show');
        Route::post('/duplicate-candidates/{candidate}/decision', [DuplicateCandidateController::class, 'resolve'])->name('duplicate-candidates.resolve');
        Route::get('/archive-promotions', [ArchivePromotionController::class, 'index'])->name('archive-promotions.index');
        Route::get('/archive-promotions/{incomingUpload}', [ArchivePromotionController::class, 'show'])->name('archive-promotions.show');
        Route::post('/archive-promotions/{incomingUpload}', [ArchivePromotionController::class, 'store'])->name('archive-promotions.store');
        Route::get('/viewing-derivatives', [ViewingDerivativeController::class, 'index'])->name('viewing-derivatives.index');
        Route::get('/viewing-derivatives/preview/{version}', [ViewingDerivativeController::class, 'preview'])->name('viewing-derivatives.preview');
        Route::get('/viewing-derivatives/{mediaItem}', [ViewingDerivativeController::class, 'show'])->name('viewing-derivatives.show');
        Route::post('/viewing-derivatives/{mediaItem}', [ViewingDerivativeController::class, 'store'])->name('viewing-derivatives.store');
    });
});
if (file_exists(__DIR__.'/settings.php')) {
    require __DIR__.'/settings.php';
}
