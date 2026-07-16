<?php

use App\Http\Controllers\Admin\ArchiveSchemaController;
use App\Http\Controllers\Admin\ArchiveStorageController;
use App\Http\Controllers\Admin\DuplicateCandidateController;
use App\Http\Controllers\Admin\PhotoIntakeController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');
Route::match(['get', 'post'], '/register', function (): never {
    abort(404);
})->name('register');
Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::view('/dashboard', 'dashboard')->name('dashboard');
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
    });
});
if (file_exists(__DIR__.'/settings.php')) {
    require __DIR__.'/settings.php';
}
