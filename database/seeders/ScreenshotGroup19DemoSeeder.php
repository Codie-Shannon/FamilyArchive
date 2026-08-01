<?php

namespace Database\Seeders;

use App\Domain\Access\Models\ContributorSubmission;
use App\Domain\Access\Models\UploadSession;
use App\Domain\Intake\Actions\ApproveIncomingPhotoForRestoration;
use App\Domain\Intake\Enums\DuplicateStatus;
use App\Domain\Intake\Enums\IncomingProcessingStatus;
use App\Domain\Intake\Enums\IncomingReviewStatus;
use App\Domain\Intake\Models\IncomingUpload;
use App\Domain\Processing\Services\RestorationReviewService;
use App\Models\User;
use GdImage;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

final class ScreenshotGroup19DemoSeeder extends Seeder
{
    /** @var array<string, bool|string> */
    private array $preferences = [
        'automation_mode' => 'candidates',
        'auto_rotate' => true,
        'deskew' => true,
        'perspective' => true,
        'crop_target' => 'photo_edge',
        'exposure' => true,
        'color' => true,
        'denoise' => false,
        'sharpen' => false,
        'cleanup' => false,
        'damage_repair' => false,
        'upscale' => false,
        'quality_warnings' => true,
    ];

    public function run(): void
    {
        abort_unless(app()->environment('local'), 403, 'The SG19 dataset is local-only.');
        $this->assertLocalStorage();

        $owner = $this->user('sg19-owner@example.test', 'Morgan Rimu', 'owner');
        $contributor = $this->user('sg19-contributor@example.test', 'Ari Kauri', 'contributor');
        $session = UploadSession::query()->updateOrCreate(
            ['session_id' => '19000000-0000-4000-8000-000000000001'],
            [
                'user_id' => $contributor->id,
                'title' => 'Fictional framed-photo test set',
                'source_context' => 'Synthetic photographs created only for the SG19 verified workflow evidence.',
                'automation_preferences' => $this->preferences,
                'expected_files' => 12,
                'received_files' => 2,
                'status' => 'paused',
                'expires_at' => now()->addDays(14),
            ],
        );

        $complete = $this->upload(
            contributor: $contributor,
            session: $session,
            uploadId: 'UP-SG19-DEMO-COMPLETE',
            submissionId: 'SUB-SG19-DEMO-COMPLETE',
            filename: 'fictional-framed-anniversary.jpg',
            path: 'sg19-demo/quarantine/fictional-framed-anniversary.jpg',
            theme: 1,
        );
        $this->upload(
            contributor: $contributor,
            session: $session,
            uploadId: 'UP-SG19-DEMO-PENDING',
            submissionId: 'SUB-SG19-DEMO-PENDING',
            filename: 'fictional-album-portrait.jpg',
            path: 'sg19-demo/quarantine/fictional-album-portrait.jpg',
            theme: 2,
        );

        $result = app(ApproveIncomingPhotoForRestoration::class)->handle($complete, $owner);
        if ($result->candidate === null) {
            throw new RuntimeException('The fictional SG19 photo did not produce a restoration candidate.');
        }

        if ($result->candidate->review_state === 'pending') {
            app(RestorationReviewService::class)->decide(
                $result->candidate,
                $owner,
                'approved',
                'Approved fictional edge recovery after comparing it with the retained source.',
            );
        }

        $item = $result->promotion?->mediaItem;
        if ($item === null) {
            throw new RuntimeException('The fictional SG19 workflow did not create an archive item.');
        }
        $item->forceFill([
            'title' => 'Fictional anniversary portrait',
            'description' => 'Synthetic framed-photo test record. No real people or family media are shown.',
            'story' => 'A privacy-safe workflow record proving retained-source acceptance, owner-approved restoration, and private viewing derivatives.',
            'estimated_decade' => 1970,
        ])->save();
    }

    private function user(string $email, string $name, string $role): User
    {
        $user = User::query()->firstOrNew(['email' => $email]);
        $user->forceFill([
            'name' => $name,
            'password' => Hash::make('SG19Demo!2026'),
            'email_verified_at' => now(),
            'role' => $role,
            'account_state' => 'approved',
            'family_connection' => 'Fictional SG19 evidence identity',
        ])->save();

        return $user;
    }

    private function upload(
        User $contributor,
        UploadSession $session,
        string $uploadId,
        string $submissionId,
        string $filename,
        string $path,
        int $theme,
    ): IncomingUpload {
        $bytes = $this->imageBytes($theme);
        $this->putDeterministic('archive_quarantine', $path, $bytes);
        $facts = getimagesizefromstring($bytes);
        if (! is_array($facts)) {
            throw new RuntimeException('The fictional SG19 source image could not be decoded.');
        }

        $upload = IncomingUpload::query()->updateOrCreate(
            ['upload_id' => $uploadId],
            [
                'uploader_id' => $contributor->id,
                'original_filename' => $filename,
                'incoming_path' => $path,
                'mime_type' => 'image/jpeg',
                'extension' => 'jpg',
                'file_size_bytes' => strlen($bytes),
                'width' => $facts[0],
                'height' => $facts[1],
                'sha256' => hash('sha256', $bytes),
                'processing_status' => IncomingProcessingStatus::Pending,
                'review_status' => IncomingReviewStatus::PendingReview,
                'duplicate_status' => DuplicateStatus::NotChecked,
                'source_file_retained' => true,
                'retained_at' => now()->subMinutes(8),
                'submitted_at' => now()->subMinutes(8),
            ],
        );

        ContributorSubmission::query()->updateOrCreate(
            ['submission_id' => $submissionId],
            [
                'user_id' => $contributor->id,
                'upload_session_id' => $session->id,
                'incoming_upload_id' => $upload->id,
                'status' => 'pending',
                'original_name' => $filename,
                'source_context' => $session->source_context,
                'proposed_metadata' => [
                    'title' => $theme === 1 ? 'Fictional anniversary portrait' : 'Fictional album portrait',
                    'evidence_scope' => 'synthetic-only',
                ],
                'automation_preferences' => $this->preferences,
            ],
        );

        return $upload->fresh();
    }

    private function imageBytes(int $theme): string
    {
        $width = 1400;
        $height = 1000;
        $image = imagecreatetruecolor($width, $height);
        if (! $image instanceof GdImage) {
            throw new RuntimeException('Unable to allocate an SG19 fictional image.');
        }

        $background = $this->color($image, 219, 211, 196);
        $frame = $theme === 1 ? $this->color($image, 72, 52, 38) : $this->color($image, 45, 58, 72);
        $mat = $this->color($image, 239, 228, 201);
        $photo = $theme === 1 ? $this->color($image, 105, 143, 139) : $this->color($image, 139, 118, 151);
        $dark = $this->color($image, 31, 35, 39);
        $light = $this->color($image, 250, 246, 232);
        $accent = $theme === 1 ? $this->color($image, 171, 79, 73) : $this->color($image, 87, 126, 176);

        imagefilledrectangle($image, 0, 0, $width, $height, $background);
        imagefilledrectangle($image, 95, 70, 1305, 930, $frame);
        imagefilledrectangle($image, 140, 115, 1260, 885, $mat);
        imagefilledrectangle($image, 205, 165, 1195, 820, $photo);
        imagefilledellipse($image, 520, 430, 260, 315, $light);
        imagefilledellipse($image, 880, 420, 260, 315, $light);
        imagefilledrectangle($image, 390, 570, 650, 800, $accent);
        imagefilledrectangle($image, 750, 560, 1010, 800, $dark);
        imagefilledellipse($image, 520, 430, 105, 135, $dark);
        imagefilledellipse($image, 880, 420, 105, 135, $dark);
        imagestring($image, 5, 225, 190, 'FICTIONAL SG19 PHOTO - NO REAL PEOPLE', $light);
        imagestring($image, 5, 455, 850, 'PRIVACY-SAFE WORKFLOW EVIDENCE', $dark);

        ob_start();
        $encoded = imagejpeg($image, null, 91);
        $bytes = ob_get_clean();
        unset($image);
        if (! $encoded || $bytes === '') {
            throw new RuntimeException('Unable to encode an SG19 fictional image.');
        }

        return $bytes;
    }

    private function color(GdImage $image, int $red, int $green, int $blue): int
    {
        $color = imagecolorallocate(
            $image,
            max(0, min(255, $red)),
            max(0, min(255, $green)),
            max(0, min(255, $blue)),
        );
        if ($color === false) {
            throw new RuntimeException('Unable to allocate an SG19 fictional image colour.');
        }

        return $color;
    }

    private function putDeterministic(string $disk, string $path, string $bytes): void
    {
        if (Storage::disk($disk)->exists($path)) {
            $existing = Storage::disk($disk)->get($path);
            if (! hash_equals(hash('sha256', $bytes), hash('sha256', $existing))) {
                throw new RuntimeException("The existing SG19 object at {$disk}:{$path} is not deterministic.");
            }

            return;
        }

        if (! Storage::disk($disk)->put($path, $bytes)) {
            throw new RuntimeException("Unable to write the SG19 object at {$disk}:{$path}.");
        }
    }

    private function assertLocalStorage(): void
    {
        foreach (['archive_quarantine', 'archive_originals', 'archive_derivatives'] as $disk) {
            if (config("filesystems.disks.{$disk}.driver") !== 'local') {
                throw new RuntimeException("SG19 refuses to write to the non-local {$disk} disk.");
            }
        }
    }
}
