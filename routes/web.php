<?php

use App\Http\Controllers\Admin\ArchiveSchemaController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

// The Livewire login view expects a named "register" route. Registration is
// deliberately disabled, so this placeholder keeps the login view renderable
// while every registration request returns HTTP 404.
Route::match(['get', 'post'], '/register', function (): never {
    abort(404);
})->name('register');

Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::view('/dashboard', 'dashboard')
        ->name('dashboard');

    Route::view('/admin', 'admin.dashboard')
        ->middleware('owner')
        ->name('admin.dashboard');

    Route::get('/admin/archive-schema', ArchiveSchemaController::class)
        ->middleware('owner')
        ->name('admin.archive-schema');
});

if (file_exists(__DIR__.'/settings.php')) {
    require __DIR__.'/settings.php';
}
