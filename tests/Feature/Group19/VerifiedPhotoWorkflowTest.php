<?php

use App\Domain\Access\Models\ContributorSubmission;
use App\Domain\Intake\Actions\ApproveIncomingPhotoForRestoration;
use App\Domain\Intake\Enums\DuplicateStatus;
use App\Domain\Intake\Models\IncomingUpload;
use App\Domain\Media\Enums\MediaFileVersionType;
use App\Domain\Media\Models\MediaFileVersion;
use App\Domain\Processing\Services\RestorationReviewService;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('archive_quarantine');
    Storage::fake('archive_originals');
    Storage::fake('archive_derivatives');
    Storage::fake('archive_manifests');
});

it('carries a retained contributor photo through owner approval to private restored viewing copies', function () {
    $this->withoutVite();
    $owner = User::factory()->create([
        'role' => 'owner',
        'account_state' => 'approved',
        'email_verified_at' => now(),
    ]);
    $contributor = User::factory()->create([
        'role' => 'contributor',
        'account_state' => 'approved',
        'email_verified_at' => now(),
    ]);
    $bytes = sg19FictionalPhoto();
    $path = 'sg19-test/quarantine/fictional-framed-photo.jpg';
    Storage::disk('archive_quarantine')->put($path, $bytes);
    $upload = IncomingUpload::factory()->create([
        'uploader_id' => $contributor->id,
        'incoming_path' => $path,
        'file_size_bytes' => strlen($bytes),
        'width' => 900,
        'height' => 650,
        'sha256' => hash('sha256', $bytes),
        'duplicate_status' => DuplicateStatus::NotChecked,
    ]);
    ContributorSubmission::query()->create([
        'submission_id' => 'SUB-SG19-WORKFLOW-TEST',
        'user_id' => $contributor->id,
        'incoming_upload_id' => $upload->id,
        'status' => 'retained',
        'original_name' => 'fictional-framed-photo.jpg',
        'source_context' => 'Privacy-safe SG19 integration test',
        'automation_preferences' => [
            'automation_mode' => 'candidates',
            'auto_rotate' => true,
            'deskew' => true,
            'crop_target' => 'photo_edge',
            'exposure' => true,
            'color' => true,
            'quality_warnings' => true,
        ],
    ]);
    $retainedBefore = Storage::disk('archive_quarantine')->get($path);

    $result = app(ApproveIncomingPhotoForRestoration::class)->handle($upload, $owner);
    $candidate = $result->candidate;

    expect($result->state)->toBe('candidate_ready')
        ->and($candidate)->not->toBeNull()
        ->and($candidate?->review_state)->toBe('pending')
        ->and($candidate?->candidateVersion?->version_type)->toBe(MediaFileVersionType::EditedFull)
        ->and($candidate?->candidateVersion?->is_preferred)->toBeFalse()
        ->and(Storage::disk('archive_quarantine')->get($path))->toBe($retainedBefore);

    app(RestorationReviewService::class)->decide(
        $candidate,
        $owner,
        'approved',
        'Approved fictional restoration after source comparison.',
    );

    $item = $result->promotion?->mediaItem;
    $approved = $candidate?->candidateVersion?->fresh();
    $web = MediaFileVersion::query()
        ->where('media_item_id', $item?->id)
        ->where('version_type', MediaFileVersionType::WebDisplay)
        ->firstOrFail();
    $thumbnail = MediaFileVersion::query()
        ->where('media_item_id', $item?->id)
        ->where('version_type', MediaFileVersionType::Thumbnail)
        ->firstOrFail();

    expect($candidate?->fresh()->review_state)->toBe('approved')
        ->and($approved?->is_preferred)->toBeTrue()
        ->and($web->parent_version_id)->toBe($approved?->id)
        ->and($thumbnail->parent_version_id)->toBe($approved?->id)
        ->and($web->is_preferred)->toBeTrue()
        ->and($thumbnail->is_preferred)->toBeTrue()
        ->and(Storage::disk('archive_quarantine')->get($path))->toBe($retainedBefore)
        ->and(Storage::disk('archive_originals')->exists($result->promotion?->target_path))->toBeTrue()
        ->and(Storage::disk('archive_derivatives')->exists($web->storage_path))->toBeTrue()
        ->and(Storage::disk('archive_derivatives')->exists($thumbnail->storage_path))->toBeTrue();

    $this->actingAs($owner)
        ->get(route('archive.photos.show', $item))
        ->assertOk()
        ->assertSee('derived from owner-approved restoration')
        ->assertSee(route('archive.derivatives.preview', $web), false)
        ->assertDontSee($web->storage_path);
    $this->actingAs($owner)
        ->get(route('archive.derivatives.preview', $web))
        ->assertOk()
        ->assertHeader('Content-Type', 'image/webp');
    $this->actingAs($owner)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee(route('archive.derivatives.preview', $thumbnail), false)
        ->assertDontSee($thumbnail->storage_path);
    $this->actingAs($owner)
        ->get(route('dashboard', ['photo' => $item?->id]))
        ->assertOk()
        ->assertSee('Focused archive update')
        ->assertSee(route('archive.derivatives.preview', $thumbnail), false);
});

function sg19FictionalPhoto(): string
{
    $image = imagecreatetruecolor(900, 650);
    if (! $image instanceof GdImage) {
        throw new RuntimeException('The SG19 fictional photo could not be allocated.');
    }
    imagefill($image, 0, 0, imagecolorallocate($image, 235, 230, 216));
    imagefilledrectangle($image, 80, 55, 820, 595, imagecolorallocate($image, 55, 45, 39));
    imagefilledrectangle($image, 115, 90, 785, 560, imagecolorallocate($image, 228, 214, 184));
    imagefilledrectangle($image, 155, 125, 745, 520, imagecolorallocate($image, 75, 120, 125));
    imagefilledellipse($image, 345, 295, 150, 190, imagecolorallocate($image, 245, 236, 216));
    imagefilledellipse($image, 570, 290, 150, 190, imagecolorallocate($image, 245, 236, 216));
    imagestring($image, 5, 180, 145, 'FICTIONAL SG19 PHOTO', imagecolorallocate($image, 255, 255, 255));
    ob_start();
    $encoded = imagejpeg($image, null, 90);
    $bytes = ob_get_clean();
    unset($image);

    if (! $encoded || ! is_string($bytes) || $bytes === '') {
        throw new RuntimeException('The SG19 fictional photo could not be encoded.');
    }

    return $bytes;
}
