<?php

use App\Http\Controllers\Admin\ArchivePromotionController;
use App\Http\Controllers\Admin\ArchiveSchemaController;
use App\Http\Controllers\Admin\ArchiveStorageController;
use App\Http\Controllers\Admin\DuplicateCandidateController;
use App\Http\Controllers\Admin\PhotoIntakeController;
use App\Http\Controllers\Admin\ViewingDerivativeController;
use App\Http\Controllers\Archive\ArchiveBrowseController;
use App\Http\Controllers\Archive\ArchiveEventController;
use App\Http\Controllers\Archive\ArchiveLocationController;
use App\Http\Controllers\Archive\ArchivePersonController;
use App\Http\Controllers\Archive\EventMediaController;
use App\Http\Controllers\Archive\EventProvenanceController;
use App\Http\Controllers\Archive\FamilyBranchController;
use App\Http\Controllers\Archive\FamilyBranchProvenanceController;
use App\Http\Controllers\Archive\KnowledgeHubController;
use App\Http\Controllers\Archive\PersonProvenanceController;
use App\Http\Controllers\Archive\PhotoMetadataController;
use App\Http\Controllers\Archive\PhotoMetadataHistoryController;
use App\Http\Controllers\Archive\PhotoProvenanceController;
use App\Http\Controllers\Archive\PrivateDerivativeController;
use App\Http\Controllers\Archive\ScanBatchController;
use App\Http\Controllers\Archive\SourceCollectionController;
use App\Support\Release;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');
Route::match(['get', 'post'], '/register', function (): never {
    abort(404);
})->name('register');
Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::view('/dashboard', 'dashboard')->name('dashboard');
    Route::middleware('owner')->group(function (): void {
        Route::get('/archive', [ArchiveBrowseController::class, 'index'])->name('archive.index');
        if (Release::archiveKnowledgePrototypeEnabled()) {
            Route::get('/archive/knowledge', KnowledgeHubController::class)->name('archive.knowledge');
        }
        Route::get('/archive/events', [ArchiveEventController::class, 'index'])->name('archive.events.index');
        Route::get('/archive/events/create', [ArchiveEventController::class, 'create'])->name('archive.events.create');
        Route::post('/archive/events', [ArchiveEventController::class, 'store'])->name('archive.events.store');
        Route::get('/archive/events/{archiveEvent}', [ArchiveEventController::class, 'show'])->name('archive.events.show');
        Route::get('/archive/events/{archiveEvent}/edit', [ArchiveEventController::class, 'edit'])->name('archive.events.edit');
        Route::patch('/archive/events/{archiveEvent}', [ArchiveEventController::class, 'update'])->name('archive.events.update');
        Route::post('/archive/events/{archiveEvent}/provenance', [EventProvenanceController::class, 'store'])->name('archive.events.provenance.store');
        Route::post('/archive/events/{archiveEvent}/media', [EventMediaController::class, 'store'])->name('archive.events.media.store');
        Route::get('/archive/locations', [ArchiveLocationController::class, 'index'])->name('archive.locations.index');
        Route::get('/archive/locations/create', [ArchiveLocationController::class, 'create'])->name('archive.locations.create');
        Route::post('/archive/locations', [ArchiveLocationController::class, 'store'])->name('archive.locations.store');
        Route::get('/archive/locations/{archiveLocation}', [ArchiveLocationController::class, 'show'])->name('archive.locations.show');
        Route::get('/archive/locations/{archiveLocation}/edit', [ArchiveLocationController::class, 'edit'])->name('archive.locations.edit');
        Route::patch('/archive/locations/{archiveLocation}', [ArchiveLocationController::class, 'update'])->name('archive.locations.update');
        Route::get('/archive/people', [ArchivePersonController::class, 'index'])->name('archive.people.index');
        Route::get('/archive/people/create', [ArchivePersonController::class, 'create'])->name('archive.people.create');
        Route::post('/archive/people', [ArchivePersonController::class, 'store'])->name('archive.people.store');
        Route::get('/archive/people/{archivePerson}', [ArchivePersonController::class, 'show'])->name('archive.people.show');
        Route::get('/archive/people/{archivePerson}/edit', [ArchivePersonController::class, 'edit'])->name('archive.people.edit');
        Route::patch('/archive/people/{archivePerson}', [ArchivePersonController::class, 'update'])->name('archive.people.update');
        Route::post('/archive/people/{archivePerson}/provenance', [PersonProvenanceController::class, 'store'])->name('archive.people.provenance.store');
        Route::get('/archive/family-branches', [FamilyBranchController::class, 'index'])->name('archive.branches.index');
        Route::get('/archive/family-branches/create', [FamilyBranchController::class, 'create'])->name('archive.branches.create');
        Route::post('/archive/family-branches', [FamilyBranchController::class, 'store'])->name('archive.branches.store');
        Route::get('/archive/family-branches/{familyBranch}', [FamilyBranchController::class, 'show'])->name('archive.branches.show');
        Route::get('/archive/family-branches/{familyBranch}/edit', [FamilyBranchController::class, 'edit'])->name('archive.branches.edit');
        Route::patch('/archive/family-branches/{familyBranch}', [FamilyBranchController::class, 'update'])->name('archive.branches.update');
        Route::post('/archive/family-branches/{familyBranch}/provenance', [FamilyBranchProvenanceController::class, 'store'])->name('archive.branches.provenance.store');
        Route::get('/archive/photos/{mediaItem}', [ArchiveBrowseController::class, 'show'])->name('archive.photos.show');
        Route::get('/archive/photos/{mediaItem}/edit', [PhotoMetadataController::class, 'edit'])->name('archive.photos.metadata.edit');
        Route::patch('/archive/photos/{mediaItem}/metadata', [PhotoMetadataController::class, 'update'])->name('archive.photos.metadata.update');
        Route::get('/archive/photos/{mediaItem}/history', [PhotoMetadataHistoryController::class, 'index'])->name('archive.photos.metadata.history');
        Route::get('/archive/photos/{mediaItem}/history/{revision}', [PhotoMetadataHistoryController::class, 'show'])->name('archive.photos.metadata.history.show');
        Route::post('/archive/photos/{mediaItem}/provenance', [PhotoProvenanceController::class, 'store'])->name('archive.photos.provenance.store');
        Route::delete('/archive/photos/{mediaItem}/provenance/{provenance}', [PhotoProvenanceController::class, 'destroy'])->name('archive.photos.provenance.destroy');
        Route::get('/archive/sources', [SourceCollectionController::class, 'index'])->name('archive.sources.index');
        Route::get('/archive/sources/create', [SourceCollectionController::class, 'create'])->name('archive.sources.create');
        Route::post('/archive/sources', [SourceCollectionController::class, 'store'])->name('archive.sources.store');
        Route::get('/archive/sources/{sourceCollection}', [SourceCollectionController::class, 'show'])->name('archive.sources.show');
        Route::post('/archive/sources/{sourceCollection}/scan-batches', [ScanBatchController::class, 'store'])->name('archive.sources.scan-batches.store');
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
