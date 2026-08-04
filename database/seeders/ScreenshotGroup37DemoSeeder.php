<?php

namespace Database\Seeders;

use App\Domain\Access\Models\ContributorSubmission;
use App\Domain\Access\Models\UploadSession;
use App\Domain\CloudImport\Services\BatchContentSafety;
use App\Domain\CloudImport\Services\BrowserUploadBatch;
use App\Domain\CloudImport\Services\TrustedBatchReview;
use App\Domain\CloudImport\ValueObjects\BatchSafetyPolicy;
use App\Domain\Intake\Services\CreateAndRetainIncomingPhoto;
use App\Models\User;
use GdImage;
use Illuminate\Database\Seeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

final class ScreenshotGroup37DemoSeeder extends Seeder
{
    private const SESSION_ID = '37000000-0000-4000-8000-000000000001';

    public function run(): void
    {
        abort_unless(app()->environment('local'), 403, 'The SG37 dataset is local-only.');

        $owner = $this->owner();

        if (! UploadSession::query()->where('session_id', self::SESSION_ID)->exists()) {
            $this->createBatch($owner);
        }

        DB::table('cloud_import_sessions')->where('session_id', self::SESSION_ID)->update([
            'user_id' => $owner->id,
            'source_manifest' => json_encode([
                'provider' => 'manual_export',
                'source_label' => 'Synthetic SG37 safeguards review',
                'content_safety' => BatchSafetyPolicy::defaults()->toArray(),
            ], JSON_THROW_ON_ERROR),
            'updated_at' => now(),
        ]);

        $this->applySafetyExamples($owner);
    }

    private function createBatch(User $owner): void
    {
        $session = DB::transaction(function () use ($owner): UploadSession {
            $session = UploadSession::query()->create([
                'session_id' => self::SESSION_ID,
                'user_id' => $owner->id,
                'title' => 'Synthetic safeguards review',
                'source_context' => 'Generated neutral evidence cards for batch content-safety review.',
                'automation_preferences' => [
                    'automation_mode' => 'candidates',
                    'crop_target' => 'photo_edge',
                    'auto_rotate' => true,
                    'deskew' => true,
                    'perspective' => true,
                    'exposure' => true,
                    'color' => true,
                    'denoise' => true,
                    'sharpen' => true,
                    'cleanup' => false,
                    'damage_repair' => false,
                    'upscale' => false,
                    'quality_warnings' => true,
                ],
                'expected_files' => 5,
                'received_files' => 0,
                'status' => 'open',
                'expires_at' => now()->addDays(14),
            ]);
            app(BrowserUploadBatch::class)->open($owner, $session);

            return $session;
        });

        $directory = storage_path('framework/testing/sg37-synthetic-safeguards');
        File::deleteDirectory($directory);
        File::ensureDirectoryExists($directory);

        try {
            foreach ($this->examples() as $position => $example) {
                $name = 'synthetic-safeguard-'.str_pad((string) $position, 2, '0', STR_PAD_LEFT).'.jpg';
                $path = $directory.'/'.$name;
                File::put($path, $this->imageBytes($example['label'], $position));
                $incoming = app(CreateAndRetainIncomingPhoto::class)->create(
                    $owner,
                    new UploadedFile($path, $name, 'image/jpeg', UPLOAD_ERR_OK, true),
                );
                $submission = ContributorSubmission::query()->create([
                    'submission_id' => 'SG37-SUB-'.str_pad((string) $position, 3, '0', STR_PAD_LEFT),
                    'user_id' => $owner->id,
                    'upload_session_id' => $session->id,
                    'incoming_upload_id' => $incoming->id,
                    'status' => 'retained',
                    'original_name' => $incoming->original_filename,
                    'source_context' => $session->source_context,
                    'proposed_metadata' => ['session_title' => $session->title],
                    'automation_preferences' => $session->automation_preferences,
                ]);
                app(BrowserUploadBatch::class)->retain($session->fresh(), $submission, $incoming);
            }
            app(BrowserUploadBatch::class)->checkpoint($session->fresh());
        } finally {
            File::deleteDirectory($directory);
        }

        app(TrustedBatchReview::class)->prepare(self::SESSION_ID, $owner, 25);
    }

    private function applySafetyExamples(User $owner): void
    {
        $sessionKey = DB::table('cloud_import_sessions')->where('session_id', self::SESSION_ID)->value('id');
        if ($sessionKey === null) {
            throw new RuntimeException('The SG37 review batch was not prepared correctly.');
        }

        foreach ($this->examples() as $position => $example) {
            $item = DB::table('cloud_import_items')
                ->where('cloud_import_session_id', $sessionKey)
                ->where('position', $position)
                ->first();
            if ($item === null) {
                throw new RuntimeException('An SG37 review item is missing.');
            }

            $metadata = json_decode((string) $item->source_metadata, true);
            $metadata = is_array($metadata) ? $metadata : [];
            $metadata['content_safety'] = [
                'classification' => $example['classification'],
                'document_year' => $example['year'],
                'classified_by' => $owner->id,
                'classified_at' => now()->toIso8601String(),
            ];

            DB::table('cloud_import_items')->where('id', $item->id)->update([
                'source_metadata' => json_encode($metadata, JSON_THROW_ON_ERROR),
                'attention_code' => $position === 1 ? null : 'safety_review',
                'updated_at' => now(),
            ]);
        }

        DB::table('cloud_import_sessions')->where('id', $sessionKey)->update([
            'attention_count' => 4,
            'review_state' => 'needs_attention',
            'updated_at' => now(),
        ]);
    }

    /** @return array<int, array{label:string, classification:string, year:?int}> */
    private function examples(): array
    {
        return [
            1 => ['label' => 'CLEAR FAMILY PHOTO', 'classification' => BatchContentSafety::CLEAR, 'year' => null],
            2 => ['label' => 'IDENTIFICATION REVIEW', 'classification' => BatchContentSafety::IDENTIFICATION_DOCUMENT, 'year' => null],
            3 => ['label' => 'HISTORICAL RECORD 1964', 'classification' => BatchContentSafety::HISTORICAL_IDENTIFICATION_DOCUMENT, 'year' => 1964],
            4 => ['label' => 'PERMANENT SAFETY HOLD', 'classification' => BatchContentSafety::SUSPECTED_ILLEGAL_CONTENT, 'year' => null],
            5 => ['label' => 'SENSITIVE REVIEW SAMPLE', 'classification' => BatchContentSafety::SENSITIVE_MINOR_IMAGE, 'year' => null],
        ];
    }

    private function owner(): User
    {
        $user = User::query()->firstOrNew(['email' => 'sg37-owner@example.test']);
        $user->forceFill([
            'name' => 'Morgan Hale',
            'password' => Hash::make('SG37Demo!2026'),
            'email_verified_at' => now(),
            'role' => 'owner',
            'account_state' => 'approved',
            'family_connection' => 'Fictional SG37 evidence identity',
        ])->save();

        return $user;
    }

    private function imageBytes(string $label, int $theme): string
    {
        $image = imagecreatetruecolor(1400, 1000);
        if (! $image instanceof GdImage) {
            throw new RuntimeException('Unable to allocate an SG37 synthetic image.');
        }

        $background = $this->color($image, 22, 25, 28);
        $panel = $this->color($image, 42 + ($theme * 4), 47 + ($theme * 3), 52 + ($theme * 2));
        $paper = $this->color($image, 239, 232, 217);
        $ink = $this->color($image, 31, 36, 41);
        $accent = $this->color($image, 32 + ($theme * 17), 151 - ($theme * 6), 130 + ($theme * 7));

        imagefilledrectangle($image, 0, 0, 1400, 1000, $background);
        imagefilledrectangle($image, 90, 85, 1310, 915, $panel);
        imagefilledrectangle($image, 175, 175, 1225, 825, $paper);
        imagefilledrectangle($image, 175, 175, 1225, 270, $accent);
        imagefilledellipse($image, 430, 510, 250, 250, $panel);
        imagefilledellipse($image, 970, 510, 250, 250, $panel);
        imagefilledrectangle($image, 350, 650, 1050, 710, $accent);
        imagestring($image, 5, 215, 215, $label, $ink);
        imagestring($image, 5, 490, 760, 'SYNTHETIC EVIDENCE CARD', $ink);

        ob_start();
        $encoded = imagejpeg($image, null, 91);
        $bytes = ob_get_clean();
        unset($image);

        if (! $encoded || $bytes === '') {
            throw new RuntimeException('Unable to encode an SG37 synthetic image.');
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
            throw new RuntimeException('Unable to allocate an SG37 synthetic image colour.');
        }

        return $color;
    }
}
