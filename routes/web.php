<?php

use App\Http\Controllers\Admin\AccountAccessController;
use App\Http\Controllers\Admin\ArchivePromotionController;
use App\Http\Controllers\Admin\ArchiveSchemaController;
use App\Http\Controllers\Admin\ArchiveStorageController;
use App\Http\Controllers\Admin\CloudImportController;
use App\Http\Controllers\Admin\DuplicateCandidateController;
use App\Http\Controllers\Admin\FamilyOperationsController;
use App\Http\Controllers\Admin\HighVolumeBatchController;
use App\Http\Controllers\Admin\MediaIntelligenceController;
use App\Http\Controllers\Admin\MigrationQualificationController;
use App\Http\Controllers\Admin\OperationsController;
use App\Http\Controllers\Admin\OwnerCommandCentreController;
use App\Http\Controllers\Admin\PhotoIntakeController;
use App\Http\Controllers\Admin\PortfolioShowcaseController;
use App\Http\Controllers\Admin\ProductionReadinessController;
use App\Http\Controllers\Admin\RealtimeCommunityController;
use App\Http\Controllers\Admin\ReleaseAcceptanceController;
use App\Http\Controllers\Admin\RestorationCandidatePreviewController;
use App\Http\Controllers\Admin\RestorationWorkspaceController;
use App\Http\Controllers\Admin\SecureCommunicationController;
use App\Http\Controllers\Admin\ViewingDerivativeController;
use App\Http\Controllers\Archive\ArchiveAlbumController;
use App\Http\Controllers\Archive\ArchiveBrowseController;
use App\Http\Controllers\Archive\ArchiveEventController;
use App\Http\Controllers\Archive\ArchiveLocationController;
use App\Http\Controllers\Archive\ArchivePersonController;
use App\Http\Controllers\Archive\ArchivePhotoEditorController;
use App\Http\Controllers\Archive\ArchivePhotoEditorPreviewController;
use App\Http\Controllers\Archive\ArchivePhotoPreferenceController;
use App\Http\Controllers\Archive\ArchivePhotoSplitController;
use App\Http\Controllers\Archive\ArchiveSelectionController;
use App\Http\Controllers\Archive\EventMediaController;
use App\Http\Controllers\Archive\EventProvenanceController;
use App\Http\Controllers\Archive\FamilyBranchController;
use App\Http\Controllers\Archive\FamilyBranchProvenanceController;
use App\Http\Controllers\Archive\KnowledgeHubController;
use App\Http\Controllers\Archive\OriginalMediaController;
use App\Http\Controllers\Archive\PersonProvenanceController;
use App\Http\Controllers\Archive\PhotoMetadataController;
use App\Http\Controllers\Archive\PhotoMetadataHistoryController;
use App\Http\Controllers\Archive\PhotoProvenanceController;
use App\Http\Controllers\Archive\PhotoVisibilityController;
use App\Http\Controllers\Archive\PrivateDerivativeController;
use App\Http\Controllers\Archive\ScanBatchController;
use App\Http\Controllers\Archive\SourceCollectionController;
use App\Http\Controllers\Auth\InvitationController;
use App\Http\Controllers\CommunityWorkspaceController;
use App\Http\Controllers\ContributorSubmissionController;
use App\Http\Controllers\FamilyMessageController;
use App\Http\Controllers\Intake\BatchItemPreviewController;
use App\Http\Controllers\Intake\BatchReviewController;
use App\Http\Controllers\Intake\PhotoSplitEditorController;
use App\Http\Controllers\Intake\PhotoSplitPreviewController;
use App\Http\Controllers\Intake\RestorationEditorController;
use App\Http\Controllers\MemberHomeController;
use App\Http\Controllers\PublicConversationController;
use App\Http\Controllers\PublicDiscoveryController;
use App\Http\Controllers\SecureMessagingController;
use App\Http\Controllers\WorkHomeController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');
Route::get('/discover', [PublicDiscoveryController::class, 'index'])->name('public-discovery.index');
Route::get('/discover/map', [PublicDiscoveryController::class, 'map'])->name('public-discovery.map');
Route::get('/conversations', [PublicConversationController::class, 'index'])->name('public-chat.index');
Route::post('/contact/anonymous', [PublicConversationController::class, 'anonymous'])->middleware('throttle:5,1')->name('anonymous-message.store');
Route::match(['get', 'post'], '/register', function (): never {
    abort(404);
})->name('register');
Route::middleware('guest')->group(function (): void {
    Route::get('/access-code', [InvitationController::class, 'code'])->name('access-code.show');
    Route::post('/access-code', [InvitationController::class, 'find'])->middleware('throttle:5,1')->name('access-code.find');
    Route::get('/join/{invitationId}/{token}', [InvitationController::class, 'show'])->middleware('throttle:20,1')->name('invitation.show');
    Route::post('/join/{invitationId}/{token}', [InvitationController::class, 'accept'])->middleware('throttle:10,1')->name('invitation.accept');
});
Route::view('/account/waiting', 'auth.account-waiting')->middleware('auth')->name('account.waiting');
Route::post('/conversations/messages', [PublicConversationController::class, 'message'])
    ->middleware(['auth', 'verified', 'demo.readonly', 'throttle:20,1'])
    ->name('public-chat.message');
Route::patch('/conversations/messages/{message}/report', [PublicConversationController::class, 'report'])
    ->middleware(['auth', 'verified', 'account.approved', 'demo.readonly', 'throttle:10,1'])
    ->whereNumber('message')
    ->name('public-chat.report');
Route::middleware(['auth', 'verified', 'account.approved', 'demo.readonly'])->group(function (): void {
    Route::get('/dashboard', MemberHomeController::class)->name('dashboard');
    Route::get('/work', WorkHomeController::class)->middleware('work.access')->name('work.index');
    Route::get('/community', CommunityWorkspaceController::class)->name('community.index');
    Route::redirect('/secure-messages', '/contact-requests', 301)->name('secure-messages.legacy');
    Route::get('/contact-requests', SecureMessagingController::class)->name('contact-requests.index');
    Route::patch('/contact-requests/{thread}', [SecureMessagingController::class, 'consent'])
        ->whereNumber('thread')
        ->name('contact-requests.consent');
    Route::prefix('family-messages')->name('family-messages.')->group(function (): void {
        Route::get('/', [FamilyMessageController::class, 'index'])->name('index');
        Route::post('/threads', [FamilyMessageController::class, 'storeThread'])->middleware('throttle:20,1')->name('threads.store');
        Route::get('/threads/{threadId}', [FamilyMessageController::class, 'show'])->name('threads.show');
        Route::post('/threads/{threadId}/messages', [FamilyMessageController::class, 'storeMessage'])->middleware('throttle:30,1')->name('messages.store');
        Route::patch('/threads/{threadId}/setting', [FamilyMessageController::class, 'setting'])->middleware('throttle:20,1')->name('settings.update');
        Route::patch('/messages/{messageId}/report', [FamilyMessageController::class, 'report'])->middleware('throttle:10,1')->name('messages.report');
    });
    Route::get('/archive', [ArchiveBrowseController::class, 'index'])->name('archive.index');
    Route::get('/archive/hidden', [ArchiveBrowseController::class, 'hidden'])->name('archive.photos.hidden');
    Route::get('/archive/photos/{mediaItem}', [ArchiveBrowseController::class, 'show'])->name('archive.photos.show');
    Route::patch('/archive/photo-preferences', [ArchivePhotoPreferenceController::class, 'update'])->name('archive.photos.preferences.update');
    Route::put('/archive/selections/{mediaItem}', [ArchiveSelectionController::class, 'update'])->name('archive.selections.update');
    Route::delete('/archive/selections', [ArchiveSelectionController::class, 'clear'])->name('archive.selections.clear');
    Route::get('/archive/photos/{mediaItem}/hide', [PhotoVisibilityController::class, 'hideForm'])->name('archive.photos.hide.form');
    Route::post('/archive/photos/{mediaItem}/hide', [PhotoVisibilityController::class, 'hideOne'])->name('archive.photos.hide.one');
    Route::post('/archive/photos/hide-selected', [PhotoVisibilityController::class, 'hideBatch'])->name('archive.photos.hide.batch');
    Route::post('/archive/photos/restore-selected', [PhotoVisibilityController::class, 'restoreBatch'])->name('archive.photos.restore.batch');
    Route::get('/archive/photo-editor', [ArchivePhotoEditorController::class, 'index'])->name('archive.photos.editor');
    Route::post('/archive/photo-editor/publish-all', [ArchivePhotoEditorController::class, 'publishAll'])->name('archive.photos.editor.publish-all');
    Route::get('/archive/photo-editor/{mediaItem}/split', [ArchivePhotoSplitController::class, 'edit'])->name('archive.photos.editor.split');
    Route::post('/archive/photo-editor/{mediaItem}/split', [ArchivePhotoSplitController::class, 'publish'])->name('archive.photos.editor.split.publish');
    Route::get('/archive/photo-editor/{mediaItem}/source', ArchivePhotoEditorPreviewController::class)->name('archive.photos.editor.source');
    Route::put('/archive/photo-editor/{mediaItem}/draft', [ArchivePhotoEditorController::class, 'draft'])->name('archive.photos.editor.draft');
    Route::post('/archive/photo-editor/{mediaItem}/publish', [ArchivePhotoEditorController::class, 'publish'])->name('archive.photos.editor.publish');
    Route::get('/archive/albums', [ArchiveAlbumController::class, 'index'])->name('archive.albums.index');
    Route::get('/archive/albums/create', [ArchiveAlbumController::class, 'create'])
        ->middleware('trusted.intake')
        ->name('archive.albums.create');
    Route::get('/archive/albums/{type}/{stableId}', [ArchiveAlbumController::class, 'show'])
        ->where('type', 'album|event|place|person|branch')
        ->name('archive.albums.show');
    Route::get('/archive/derivatives/{mediaFileVersion}/preview', PrivateDerivativeController::class)->name('archive.derivatives.preview');
    Route::get('/archive/originals/{mediaFileVersion}', OriginalMediaController::class)->name('archive.originals.show');
    Route::get('/archive/knowledge', KnowledgeHubController::class)->name('archive.knowledge');
    Route::get('/archive/events', [ArchiveEventController::class, 'index'])->name('archive.events.index');
    Route::get('/archive/events/{archiveEvent}', [ArchiveEventController::class, 'show'])->whereNumber('archiveEvent')->name('archive.events.show');
    Route::get('/archive/locations', [ArchiveLocationController::class, 'index'])->name('archive.locations.index');
    Route::get('/archive/locations/{archiveLocation}', [ArchiveLocationController::class, 'show'])->whereNumber('archiveLocation')->name('archive.locations.show');
    Route::get('/archive/people', [ArchivePersonController::class, 'index'])->name('archive.people.index');
    Route::get('/archive/people/{archivePerson}', [ArchivePersonController::class, 'show'])->whereNumber('archivePerson')->name('archive.people.show');
    Route::get('/archive/family-branches', [FamilyBranchController::class, 'index'])->name('archive.branches.index');
    Route::get('/archive/family-branches/{familyBranch}', [FamilyBranchController::class, 'show'])->whereNumber('familyBranch')->name('archive.branches.show');
    Route::get('/contribute', [ContributorSubmissionController::class, 'index'])->name('contributor.index');
    Route::post('/contribute/sessions', [ContributorSubmissionController::class, 'start'])->name('contributor.sessions.start');
    Route::get('/contribute/sessions/{session}', [ContributorSubmissionController::class, 'show'])->name('contributor.sessions.show');
    Route::post('/contribute/sessions/{session}/photos', [ContributorSubmissionController::class, 'upload'])->name('contributor.sessions.upload');
    Route::post('/contribute/sessions/{session}/finish', [ContributorSubmissionController::class, 'finish'])->name('contributor.sessions.finish');
    Route::middleware('trusted.intake')->prefix('intake')->name('intake.')->group(function (): void {
        Route::get('/', [BatchReviewController::class, 'index'])->name('index');
        Route::get('/batches/{sessionId}', [BatchReviewController::class, 'show'])->name('batches.show');
        Route::post('/batches/{sessionId}/prepare', [BatchReviewController::class, 'prepare'])->name('batches.prepare');
        Route::post('/batches/{sessionId}/regenerate', [BatchReviewController::class, 'regenerate'])->name('batches.regenerate');
        Route::patch('/batches/{sessionId}/safety-policy', [BatchReviewController::class, 'updateSafetyPolicy'])
            ->middleware('owner')
            ->name('batches.safety-policy');
        Route::patch('/batches/{sessionId}/review', [BatchReviewController::class, 'decide'])->name('batches.review');
        Route::get('/batches/{sessionId}/items/{itemId}/{side}', BatchItemPreviewController::class)->name('items.preview');
        Route::get('/batches/{sessionId}/items/{itemId}/edit/original', [RestorationEditorController::class, 'edit'])->name('items.editor');
        Route::post('/batches/{sessionId}/items/{itemId}/edit/original', [RestorationEditorController::class, 'update'])->name('items.editor.update');
        Route::get('/batches/{sessionId}/items/{itemId}/split/photos', [PhotoSplitEditorController::class, 'edit'])->name('items.split');
        Route::post('/batches/{sessionId}/items/{itemId}/split/photos', [PhotoSplitEditorController::class, 'update'])->name('items.split.update');
        Route::get('/batches/{sessionId}/items/{itemId}/split/photos/{regionId}', PhotoSplitPreviewController::class)->name('items.split.preview');
    });
    Route::middleware('trusted.intake')->group(function (): void {
        Route::post('/archive/albums', [ArchiveAlbumController::class, 'store'])->name('archive.albums.store');
        Route::get('/archive/albums/{curatedCollection}/photos/add', [ArchiveAlbumController::class, 'addPhotos'])->name('archive.albums.photos.add');
        Route::post('/archive/albums/{curatedCollection}/photos', [ArchiveAlbumController::class, 'attachBatch'])->name('archive.albums.photos.attach');
        Route::delete('/archive/albums/{curatedCollection}/photos/{mediaItem}', [ArchiveAlbumController::class, 'detach'])->name('archive.albums.photos.detach');
    });
    Route::middleware('owner')->group(function (): void {
        Route::get('/archive/events/create', [ArchiveEventController::class, 'create'])->name('archive.events.create');
        Route::post('/archive/events', [ArchiveEventController::class, 'store'])->name('archive.events.store');
        Route::get('/archive/events/{archiveEvent}/edit', [ArchiveEventController::class, 'edit'])->name('archive.events.edit');
        Route::patch('/archive/events/{archiveEvent}', [ArchiveEventController::class, 'update'])->name('archive.events.update');
        Route::post('/archive/events/{archiveEvent}/provenance', [EventProvenanceController::class, 'store'])->name('archive.events.provenance.store');
        Route::post('/archive/events/{archiveEvent}/media', [EventMediaController::class, 'store'])->name('archive.events.media.store');
        Route::get('/archive/locations/create', [ArchiveLocationController::class, 'create'])->name('archive.locations.create');
        Route::post('/archive/locations', [ArchiveLocationController::class, 'store'])->name('archive.locations.store');
        Route::get('/archive/locations/{archiveLocation}/edit', [ArchiveLocationController::class, 'edit'])->name('archive.locations.edit');
        Route::patch('/archive/locations/{archiveLocation}', [ArchiveLocationController::class, 'update'])->name('archive.locations.update');
        Route::get('/archive/people/create', [ArchivePersonController::class, 'create'])->name('archive.people.create');
        Route::post('/archive/people', [ArchivePersonController::class, 'store'])->name('archive.people.store');
        Route::get('/archive/people/{archivePerson}/edit', [ArchivePersonController::class, 'edit'])->name('archive.people.edit');
        Route::patch('/archive/people/{archivePerson}', [ArchivePersonController::class, 'update'])->name('archive.people.update');
        Route::post('/archive/people/{archivePerson}/provenance', [PersonProvenanceController::class, 'store'])->name('archive.people.provenance.store');
        Route::get('/archive/family-branches/create', [FamilyBranchController::class, 'create'])->name('archive.branches.create');
        Route::post('/archive/family-branches', [FamilyBranchController::class, 'store'])->name('archive.branches.store');
        Route::get('/archive/family-branches/{familyBranch}/edit', [FamilyBranchController::class, 'edit'])->name('archive.branches.edit');
        Route::patch('/archive/family-branches/{familyBranch}', [FamilyBranchController::class, 'update'])->name('archive.branches.update');
        Route::post('/archive/family-branches/{familyBranch}/provenance', [FamilyBranchProvenanceController::class, 'store'])->name('archive.branches.provenance.store');
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
    });
    Route::get('/admin', OwnerCommandCentreController::class)->middleware('owner')->name('admin.dashboard');
    Route::get('/admin/archive-schema', ArchiveSchemaController::class)->middleware('owner')->name('admin.archive-schema');
    Route::get('/admin/archive-storage', ArchiveStorageController::class)->middleware('owner')->name('admin.archive-storage');
    Route::middleware('family.operations')->prefix('admin')->name('admin.')->group(function (): void {
        Route::get('/family-operations', [FamilyOperationsController::class, 'index'])->name('family-operations.index');
        Route::post('/family-operations/invitations', [FamilyOperationsController::class, 'invite'])->name('family-operations.invitations');
        Route::post('/family-operations/accounts/{user}/recovery', [FamilyOperationsController::class, 'recovery'])->name('family-operations.recovery');
        Route::patch('/family-operations/accounts/{user}', [FamilyOperationsController::class, 'account'])->name('family-operations.accounts');
        Route::patch('/family-operations/voice/{message}', [FamilyOperationsController::class, 'voice'])->whereNumber('message')->name('family-operations.voice');
        Route::patch('/family-operations/conversations/{message}', [FamilyOperationsController::class, 'conversation'])->whereNumber('message')->name('family-operations.conversations');
        Route::patch('/family-operations/private-messages/{message}', [FamilyOperationsController::class, 'privateMessage'])->whereNumber('message')->name('family-operations.private-messages');
        Route::patch('/family-operations/anonymous/{message}', [FamilyOperationsController::class, 'anonymous'])->whereNumber('message')->name('family-operations.anonymous');
    });
    Route::middleware('owner')->prefix('admin')->name('admin.')->group(function (): void {
        Route::get('/photo-intake', [PhotoIntakeController::class, 'index'])->name('photo-intake.index');
        Route::get('/operations', OperationsController::class)->name('operations');
        Route::get('/media-intelligence', MediaIntelligenceController::class)->name('media-intelligence');
        Route::get('/cloud-imports', CloudImportController::class)->name('cloud-imports');
        Route::get('/batch-imports', HighVolumeBatchController::class)->name('batch-imports');
        Route::get('/migration-qualification', MigrationQualificationController::class)->name('migration-qualification');
        Route::get('/public-discovery', [PublicDiscoveryController::class, 'admin'])->name('public-discovery');
        Route::get('/community-operations', RealtimeCommunityController::class)->name('community-operations');
        Route::get('/secure-communication', SecureCommunicationController::class)->name('secure-communication');
        Route::get('/portfolio-showcase', PortfolioShowcaseController::class)->name('portfolio-showcase');
        Route::get('/production-readiness', ProductionReadinessController::class)->name('production-readiness');
        Route::get('/access', [AccountAccessController::class, 'index'])->name('access.index');
        Route::post('/access/invitations', [AccountAccessController::class, 'invite'])->name('access.invite');
        Route::patch('/access/users/{user}', [AccountAccessController::class, 'update'])->name('access.users.update');
        Route::post('/access/original-grants', [AccountAccessController::class, 'grant'])->name('access.grants.store');
        Route::patch('/access/original-grants/{grant}/revoke', [AccountAccessController::class, 'revoke'])->name('access.grants.revoke');
        Route::patch('/contributor-submissions/{submission}', [ContributorSubmissionController::class, 'review'])->name('contributor-submissions.review');
        Route::get('/release-acceptance', ReleaseAcceptanceController::class)->name('release-acceptance');
        Route::get('/restoration', [RestorationWorkspaceController::class, 'index'])->name('restoration');
        Route::post('/restoration/jobs', [RestorationWorkspaceController::class, 'queue'])->name('restoration.jobs.queue');
        Route::post('/restoration/jobs/{job}/process', [RestorationWorkspaceController::class, 'process'])->name('restoration.jobs.process');
        Route::patch('/restoration/candidates/{candidate}', [RestorationWorkspaceController::class, 'review'])->name('restoration.candidates.review');
        Route::get('/restoration/candidates/{candidate}/{side}', RestorationCandidatePreviewController::class)->name('restoration.candidates.preview');
        Route::post('/photo-intake', [PhotoIntakeController::class, 'store'])->name('photo-intake.store');
        Route::get('/incoming-uploads', [PhotoIntakeController::class, 'queue'])->name('photo-intake.queue');
        Route::get('/incoming-uploads/{incomingUpload}/preview', [PhotoIntakeController::class, 'preview'])->name('photo-intake.preview');
        Route::get('/incoming-uploads/{incomingUpload}', [PhotoIntakeController::class, 'show'])->name('photo-intake.show');
        Route::post('/incoming-uploads/{incomingUpload}/approve-and-process', [PhotoIntakeController::class, 'approveAndProcess'])->name('photo-intake.approve-and-process');
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
