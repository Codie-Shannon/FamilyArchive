<?php

use App\Domain\Access\Models\ContributorSubmission;
use App\Domain\Archive\Models\ArchivePromotion;
use App\Domain\Intake\Actions\ApproveIncomingPhotoForRestoration;
use App\Domain\Intake\Enums\DuplicateStatus;
use App\Domain\Intake\Models\IncomingUpload;
use App\Domain\Media\Enums\MediaFileVersionType;
use App\Domain\Processing\Models\ProcessingJob;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('archive_quarantine');
    Storage::fake('archive_originals');
    Storage::fake('archive_derivatives');
    Storage::fake('archive_manifests');
});

it('accepts a unique retained contributor photo and creates a separate review candidate', function () {
    $owner = User::factory()->create(['role' => 'owner', 'email_verified_at' => now()]);
    $contributor = User::factory()->create(['role' => 'contributor']);
    $bytes = intakeAutomationPhoto(3);
    $path = 'automation/real-intake.jpg';
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
        'submission_id' => 'SUB-AUTOMATION-TEST',
        'user_id' => $contributor->id,
        'incoming_upload_id' => $upload->id,
        'status' => 'retained',
        'original_name' => 'real-intake.jpg',
        'source_context' => 'Automated intake integration test',
        'automation_preferences' => [
            'automation_mode' => 'candidates',
            'auto_rotate' => true,
            'deskew' => true,
            'crop_target' => 'photo_edge',
            'quality_warnings' => true,
        ],
    ]);
    $sourceBefore = Storage::disk('archive_quarantine')->get($path);

    $result = app(ApproveIncomingPhotoForRestoration::class)->handle($upload, $owner);

    expect($result->state)->toBe('candidate_ready')
        ->and($upload->fresh()->duplicate_status)->toBe(DuplicateStatus::NoMatch)
        ->and(ArchivePromotion::query()->where('incoming_upload_id', $upload->id)->count())->toBe(1)
        ->and($result->promotion?->originalVersion?->version_type)->toBe(MediaFileVersionType::Original)
        ->and($result->candidate?->candidateVersion?->version_type)->toBe(MediaFileVersionType::EditedFull)
        ->and($result->candidate?->candidateVersion?->parent_version_id)->toBe($result->promotion?->original_media_file_version_id)
        ->and($result->candidate?->candidateVersion?->is_preferred)->toBeFalse()
        ->and($result->candidate?->operations_applied)->toContain('auto_rotate', 'auto_crop')
        ->and(Storage::disk('archive_quarantine')->get($path))->toBe($sourceBefore)
        ->and(ContributorSubmission::query()->where('incoming_upload_id', $upload->id)->value('status'))->toBe('accepted');

    $again = app(ApproveIncomingPhotoForRestoration::class)->handle($upload->fresh(), $owner);

    expect($again->candidate?->id)->toBe($result->candidate?->id)
        ->and(ArchivePromotion::count())->toBe(1)
        ->and(ProcessingJob::count())->toBe(1);
});

it('stops at duplicate review when the retained bytes exactly match an existing original', function () {
    $owner = User::factory()->create(['role' => 'owner', 'email_verified_at' => now()]);
    $bytes = intakeAutomationPhoto();
    $existing = IncomingUpload::factory()->create([
        'incoming_path' => 'automation/existing.jpg',
        'file_size_bytes' => strlen($bytes),
        'width' => 900,
        'height' => 650,
        'sha256' => hash('sha256', $bytes),
        'duplicate_status' => DuplicateStatus::NoMatch,
    ]);
    $upload = IncomingUpload::factory()->create([
        'incoming_path' => 'automation/new.jpg',
        'file_size_bytes' => strlen($bytes),
        'width' => 900,
        'height' => 650,
        'sha256' => hash('sha256', $bytes),
        'duplicate_status' => DuplicateStatus::NotChecked,
    ]);
    Storage::disk('archive_quarantine')->put((string) $existing->incoming_path, $bytes);
    Storage::disk('archive_quarantine')->put((string) $upload->incoming_path, $bytes);

    $result = app(ApproveIncomingPhotoForRestoration::class)->handle($upload, $owner);

    expect($result->state)->toBe('duplicate_review')
        ->and($result->duplicateCandidateIds)->toHaveCount(1)
        ->and(ArchivePromotion::count())->toBe(0)
        ->and(ProcessingJob::count())->toBe(0);
});

function intakeAutomationPhoto(?int $orientation = null): string
{
    $image = imagecreatetruecolor(900, 650);
    $paper = imagecolorallocate($image, 238, 235, 224);
    imagefill($image, 0, 0, $paper);
    imagefilledrectangle($image, 120, 80, 780, 570, imagecolorallocate($image, 31, 46, 55));
    imagefilledellipse($image, 330, 290, 170, 210, imagecolorallocate($image, 223, 194, 160));
    imagefilledellipse($image, 590, 285, 170, 210, imagecolorallocate($image, 218, 187, 151));
    ob_start();
    imagejpeg($image, null, 90);
    $bytes = ob_get_clean();
    unset($image);

    if (! is_string($bytes) || $bytes === '') {
        throw new RuntimeException('The intake automation fixture could not be encoded.');
    }

    if ($orientation === null) {
        return $bytes;
    }

    $tiff = "II\x2A\x00\x08\x00\x00\x00"
        ."\x01\x00"
        ."\x12\x01\x03\x00\x01\x00\x00\x00".chr($orientation)."\x00\x00\x00"
        ."\x00\x00\x00\x00";
    $payload = "Exif\0\0".$tiff;
    $segment = "\xFF\xE1".pack('n', strlen($payload) + 2).$payload;

    return substr($bytes, 0, 2).$segment.substr($bytes, 2);
}
