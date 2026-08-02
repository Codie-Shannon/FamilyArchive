<?php

use App\Domain\CloudImport\Services\HighVolumePhotoBatch;
use App\Domain\CloudImport\Services\TrustedBatchReview;
use App\Domain\Media\Enums\MediaReviewStatus;
use App\Domain\Media\Models\MediaItem;
use App\Domain\Processing\Models\RestorationCandidate;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Storage::fake('archive_quarantine');
    Storage::fake('archive_originals');
    Storage::fake('archive_derivatives');
    Storage::fake('archive_manifests');
});

it('gives owner admin and trusted contributors the consolidated intake workspace', function (): void {
    $this->withoutVite();
    $owner = User::factory()->create(['role' => 'owner', 'email_verified_at' => now()]);
    $admin = User::factory()->create(['role' => 'admin', 'email_verified_at' => now()]);
    $trusted = User::factory()->create(['role' => 'trusted_contributor', 'email_verified_at' => now()]);
    $contributor = User::factory()->create(['role' => 'contributor', 'email_verified_at' => now()]);

    $this->actingAs($owner)->get(route('intake.index'))->assertOk()->assertSeeText('Intake & Review');
    $this->actingAs($admin)->get(route('intake.index'))->assertOk();
    $this->actingAs($trusted)->get(route('intake.index'))->assertOk();
    $this->actingAs($contributor)->get(route('intake.index'))->assertForbidden();
});

it('prepares a trusted batch once and applies a bulk original decision', function (): void {
    $trusted = User::factory()->create(['role' => 'trusted_contributor', 'email_verified_at' => now()]);
    $directory = storage_path('framework/testing/trusted-batch-'.str()->random(10));
    File::ensureDirectoryExists($directory);
    $photo = UploadedFile::fake()->image('family-photo.jpg', 900, 650);
    File::copy($photo->getRealPath(), $directory.'/family-photo.jpg');

    try {
        $planned = app(HighVolumePhotoBatch::class)->plan($trusted, $directory, 25);
        app(HighVolumePhotoBatch::class)->process($planned['session_id'], $directory, 1);

        $prepared = app(TrustedBatchReview::class)->prepare($planned['session_id'], $trusted, 25);
        $item = DB::table('cloud_import_items')->first();
        expect($prepared['prepared'])->toBe(1)
            ->and($item->prepared_at)->not->toBeNull()
            ->and($item->restoration_candidate_id)->not->toBeNull()
            ->and(MediaItem::query()->firstOrFail()->review_status)->toBe(MediaReviewStatus::PendingReview);

        $this->withoutVite()->actingAs($trusted)
            ->get(route('intake.batches.show', $planned['session_id']))
            ->assertOk()
            ->assertSee('Original')
            ->assertSee('Suggested');

        $result = app(TrustedBatchReview::class)->decide($planned['session_id'], $trusted, [(int) $item->id], 'original');
        expect($result)->toBe(['reviewed' => 1, 'failed' => 0])
            ->and(MediaItem::query()->firstOrFail()->review_status)->toBe(MediaReviewStatus::Approved)
            ->and(DB::table('cloud_import_items')->value('review_decision'))->toBe('original')
            ->and(DB::table('cloud_import_sessions')->value('review_state'))->toBe('completed')
            ->and(Storage::disk('archive_derivatives')->allFiles())->toHaveCount(3);
    } finally {
        File::deleteDirectory($directory);
    }
});

it('prevents a trusted contributor from opening another account batch', function (): void {
    $owner = User::factory()->create(['role' => 'owner', 'email_verified_at' => now()]);
    $trusted = User::factory()->create(['role' => 'trusted_contributor', 'email_verified_at' => now()]);
    $directory = storage_path('framework/testing/private-batch-'.str()->random(10));
    File::ensureDirectoryExists($directory);
    $photo = UploadedFile::fake()->image('private-photo.jpg', 80, 60);
    File::copy($photo->getRealPath(), $directory.'/private-photo.jpg');

    try {
        $planned = app(HighVolumePhotoBatch::class)->plan($owner, $directory, 25);
        $this->actingAs($trusted)->get(route('intake.batches.show', $planned['session_id']))->assertForbidden();
        $this->actingAs($owner)->get(route('intake.batches.show', $planned['session_id']))->assertOk();
    } finally {
        File::deleteDirectory($directory);
    }
});

it('regenerates only unreviewed suggestions while preserving immutable originals and audit history', function (): void {
    $trusted = User::factory()->create(['role' => 'trusted_contributor', 'email_verified_at' => now()]);
    $directory = storage_path('framework/testing/regenerate-batch-'.str()->random(10));
    File::ensureDirectoryExists($directory);
    $photo = UploadedFile::fake()->image('album-capture.jpg', 900, 650);
    File::copy($photo->getRealPath(), $directory.'/album-capture.jpg');

    try {
        $planned = app(HighVolumePhotoBatch::class)->plan($trusted, $directory, 25);
        app(HighVolumePhotoBatch::class)->process($planned['session_id'], $directory, 1);
        app(TrustedBatchReview::class)->prepare($planned['session_id'], $trusted, 25);

        $before = DB::table('cloud_import_items')->first();
        $oldCandidate = RestorationCandidate::query()->findOrFail((int) $before->restoration_candidate_id);
        $originalVersionId = MediaItem::query()->firstOrFail()->preferred_original_version_id;

        $result = app(TrustedBatchReview::class)->regeneratePending($planned['session_id'], $trusted, 25);
        $after = DB::table('cloud_import_items')->first();

        expect($result['regenerated'])->toBe(1)
            ->and($result['failed'])->toBe(0)
            ->and((int) $after->restoration_candidate_id)->not->toBe($oldCandidate->id)
            ->and($after->review_decision)->toBeNull()
            ->and(MediaItem::query()->firstOrFail()->preferred_original_version_id)->toBe($originalVersionId)
            ->and($oldCandidate->fresh()->review_state)->toBe('rejected')
            ->and($oldCandidate->fresh()->review_note)->toContain('Superseded');

        $this->withoutVite()->actingAs($trusted)
            ->get(route('intake.batches.show', $planned['session_id']))
            ->assertOk()
            ->assertSeeText('Regenerate pending suggestions');
    } finally {
        File::deleteDirectory($directory);
    }
});

it('lets trusted reviewers create a manual candidate without changing the immutable original', function (): void {
    $trusted = User::factory()->create(['role' => 'trusted_contributor', 'email_verified_at' => now()]);
    $directory = storage_path('framework/testing/manual-editor-'.str()->random(10));
    File::ensureDirectoryExists($directory);
    $photo = UploadedFile::fake()->image('framed-family-photo.jpg', 1000, 800);
    File::copy($photo->getRealPath(), $directory.'/framed-family-photo.jpg');

    try {
        $planned = app(HighVolumePhotoBatch::class)->plan($trusted, $directory, 25);
        app(HighVolumePhotoBatch::class)->process($planned['session_id'], $directory, 1);
        app(TrustedBatchReview::class)->prepare($planned['session_id'], $trusted, 25);

        $before = DB::table('cloud_import_items')->first();
        $oldCandidate = RestorationCandidate::query()->findOrFail((int) $before->restoration_candidate_id);
        $mediaItem = MediaItem::query()->firstOrFail();
        $original = $mediaItem->fileVersions()
            ->where('version_type', 'original')
            ->where('is_preferred', true)
            ->firstOrFail();
        $originalHash = $original->sha256;
        $originalBytes = Storage::disk('archive_originals')->get($original->storage_path);

        $this->withoutVite()->actingAs($trusted)
            ->get(route('intake.items.editor', [$planned['session_id'], $before->id]))
            ->assertOk()
            ->assertSeeText('Edit original')
            ->assertSeeText('Crop framing')
            ->assertSeeText('Surface cleanup');

        $response = $this->actingAs($trusted)->post(route('intake.items.editor.update', [$planned['session_id'], $before->id]), [
            'orient' => 1,
            'quarter_turn' => 0,
            'straighten' => 1.5,
            'crop_left' => 10,
            'crop_top' => 5,
            'crop_right' => 10,
            'crop_bottom' => 5,
            'brightness' => 4,
            'contrast' => 2,
            'red' => 1,
            'green' => 0,
            'blue' => -1,
            'denoise' => 1,
            'sharpen' => 1,
            'cleanup' => 0,
        ]);
        $response->assertRedirect(route('intake.batches.show', [$planned['session_id'], 'filter' => 'pending']));

        $after = DB::table('cloud_import_items')->first();
        $newCandidate = RestorationCandidate::query()->with(['candidateVersion', 'job.recipe'])->findOrFail((int) $after->restoration_candidate_id);
        $freshOriginal = $original->fresh();

        expect($newCandidate->id)->not->toBe($oldCandidate->id)
            ->and($newCandidate->review_state)->toBe('pending')
            ->and($newCandidate->analysis['editor'])->toBe('manual')
            ->and($newCandidate->job->recipe->automation_source)->toBe('manual_editor')
            ->and($newCandidate->candidateVersion->is_preferred)->toBeFalse()
            ->and($oldCandidate->fresh()->review_state)->toBe('rejected')
            ->and($oldCandidate->fresh()->review_note)->toContain('manual adjustment')
            ->and($freshOriginal->sha256)->toBe($originalHash)
            ->and(Storage::disk('archive_originals')->get($freshOriginal->storage_path))->toBe($originalBytes)
            ->and(DB::table('processing_job_events')->where('event', 'manual_candidate_ready')->exists())->toBeTrue();
    } finally {
        File::deleteDirectory($directory);
    }
});

it('lets trusted reviewers edit the original when automation has no usable suggestion', function (): void {
    $trusted = User::factory()->create(['role' => 'trusted_contributor', 'email_verified_at' => now()]);
    $directory = storage_path('framework/testing/manual-editor-without-suggestion-'.str()->random(10));
    File::ensureDirectoryExists($directory);
    $photo = UploadedFile::fake()->image('manual-original.jpg', 800, 600);
    File::copy($photo->getRealPath(), $directory.'/manual-original.jpg');

    try {
        $planned = app(HighVolumePhotoBatch::class)->plan($trusted, $directory, 25);
        app(HighVolumePhotoBatch::class)->process($planned['session_id'], $directory, 1);
        app(TrustedBatchReview::class)->prepare($planned['session_id'], $trusted, 25);
        $item = DB::table('cloud_import_items')->first();
        $supersededCandidateId = (int) $item->restoration_candidate_id;
        DB::table('cloud_import_items')->where('id', $item->id)->update([
            'restoration_candidate_id' => null,
            'attention_code' => 'crop_check',
        ]);

        $this->withoutVite()->actingAs($trusted)
            ->get(route('intake.items.editor', [$planned['session_id'], $item->id]))
            ->assertOk()
            ->assertSeeText('Edit original')
            ->assertSeeText('Automation did not create a usable suggestion');

        $this->actingAs($trusted)
            ->post(route('intake.items.editor.update', [$planned['session_id'], $item->id]), [
                'orient' => 1,
                'quarter_turn' => 1,
                'straighten' => 0,
                'crop_left' => 0,
                'crop_top' => 0,
                'crop_right' => 0,
                'crop_bottom' => 0,
                'brightness' => 0,
                'contrast' => 0,
                'red' => 0,
                'green' => 0,
                'blue' => 0,
                'denoise' => 0,
                'sharpen' => 0,
                'cleanup' => 0,
            ])
            ->assertRedirect(route('intake.batches.show', [$planned['session_id'], 'filter' => 'pending']));

        $freshItem = DB::table('cloud_import_items')->where('id', $item->id)->first();
        $manualCandidate = RestorationCandidate::query()->findOrFail((int) $freshItem->restoration_candidate_id);

        expect($manualCandidate->analysis['editor'])->toBe('manual')
            ->and($manualCandidate->source_version_id)->not->toBeNull()
            ->and($manualCandidate->operations_applied)->toContain('rotate')
            ->and(RestorationCandidate::query()->findOrFail($supersededCandidateId)->review_state)->toBe('pending');
    } finally {
        File::deleteDirectory($directory);
    }
});

it('rejects empty or destructive manual editor settings', function (): void {
    $trusted = User::factory()->create(['role' => 'trusted_contributor', 'email_verified_at' => now()]);
    $directory = storage_path('framework/testing/manual-editor-validation-'.str()->random(10));
    File::ensureDirectoryExists($directory);
    $photo = UploadedFile::fake()->image('validation-photo.jpg', 300, 200);
    File::copy($photo->getRealPath(), $directory.'/validation-photo.jpg');

    try {
        $planned = app(HighVolumePhotoBatch::class)->plan($trusted, $directory, 25);
        app(HighVolumePhotoBatch::class)->process($planned['session_id'], $directory, 1);
        app(TrustedBatchReview::class)->prepare($planned['session_id'], $trusted, 25);
        $item = DB::table('cloud_import_items')->first();
        $candidateId = (int) $item->restoration_candidate_id;

        $defaults = [
            'orient' => 1, 'quarter_turn' => 0, 'straighten' => 0,
            'crop_left' => 0, 'crop_top' => 0, 'crop_right' => 0, 'crop_bottom' => 0,
            'brightness' => 0, 'contrast' => 0, 'red' => 0, 'green' => 0, 'blue' => 0,
            'denoise' => 0, 'sharpen' => 0, 'cleanup' => 0,
        ];
        $this->actingAs($trusted)
            ->from(route('intake.items.editor', [$planned['session_id'], $item->id]))
            ->post(route('intake.items.editor.update', [$planned['session_id'], $item->id]), $defaults)
            ->assertSessionHasErrors('editor');

        $destructive = [...$defaults, 'crop_left' => 45, 'crop_right' => 45, 'brightness' => 1];
        $this->actingAs($trusted)
            ->from(route('intake.items.editor', [$planned['session_id'], $item->id]))
            ->post(route('intake.items.editor.update', [$planned['session_id'], $item->id]), $destructive)
            ->assertSessionHasErrors('crop_left');

        expect((int) DB::table('cloud_import_items')->value('restoration_candidate_id'))->toBe($candidateId);
    } finally {
        File::deleteDirectory($directory);
    }
});
