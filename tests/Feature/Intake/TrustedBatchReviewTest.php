<?php

use App\Domain\CloudImport\Services\HighVolumePhotoBatch;
use App\Domain\CloudImport\Services\TrustedBatchReview;
use App\Domain\Media\Enums\MediaReviewStatus;
use App\Domain\Media\Models\MediaItem;
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
