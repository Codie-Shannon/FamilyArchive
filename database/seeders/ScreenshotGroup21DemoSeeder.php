<?php

namespace Database\Seeders;

use App\Domain\CloudImport\Services\HighVolumePhotoBatch;
use App\Domain\CloudImport\Services\TrustedBatchReview;
use App\Models\User;
use GdImage;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

final class ScreenshotGroup21DemoSeeder extends Seeder
{
    private const SESSION_ID = '21000000-0000-4000-8000-000000000001';

    public function run(): void
    {
        abort_unless(app()->environment('local'), 403, 'The SG21 dataset is local-only.');

        $reviewer = User::query()->firstOrNew(['email' => 'sg21-reviewer@example.test']);
        $reviewer->forceFill([
            'name' => 'Ari Kauri',
            'password' => Hash::make('SG21Demo!2026'),
            'email_verified_at' => now(),
            'role' => 'trusted_contributor',
            'account_state' => 'approved',
            'family_connection' => 'Fictional SG21 evidence identity',
        ])->save();

        if (DB::table('cloud_import_sessions')->where('session_id', self::SESSION_ID)->exists()) {
            DB::table('cloud_import_sessions')->where('session_id', self::SESSION_ID)->update([
                'user_id' => $reviewer->id,
                'updated_at' => now(),
            ]);

            return;
        }

        $directory = storage_path('framework/testing/sg21-fictional-batch');
        File::deleteDirectory($directory);
        File::ensureDirectoryExists($directory);

        try {
            foreach (range(1, 6) as $theme) {
                File::put(
                    $directory.'/fictional-family-photo-'.str_pad((string) $theme, 2, '0', STR_PAD_LEFT).'.jpg',
                    $this->imageBytes($theme),
                );
            }

            $planned = app(HighVolumePhotoBatch::class)->plan($reviewer, $directory, 25);
            DB::table('cloud_import_sessions')->where('session_id', $planned['session_id'])->update([
                'session_id' => self::SESSION_ID,
                'source_manifest' => json_encode([
                    'source_label' => 'Fictional family photo intake rehearsal',
                    'selected_count' => 6,
                    'paths_persisted' => false,
                    'approval_mode' => 'exception_first_batch_review',
                    'trust_level' => 'trusted_intake',
                    'evidence_scope' => 'synthetic-only',
                ], JSON_THROW_ON_ERROR),
            ]);
            app(HighVolumePhotoBatch::class)->process(self::SESSION_ID, $directory, 6);
        } finally {
            File::deleteDirectory($directory);
        }

        app(TrustedBatchReview::class)->prepare(self::SESSION_ID, $reviewer, 25);
        $sessionKey = DB::table('cloud_import_sessions')->where('session_id', self::SESSION_ID)->value('id');
        $firstItem = DB::table('cloud_import_items')
            ->where('cloud_import_session_id', $sessionKey)
            ->orderBy('position')
            ->first();
        $attentionItem = DB::table('cloud_import_items')
            ->where('cloud_import_session_id', $sessionKey)
            ->where('position', 4)
            ->first();

        if ($firstItem === null || $attentionItem === null) {
            throw new RuntimeException('The SG21 review batch was not prepared correctly.');
        }

        DB::table('cloud_import_items')->where('id', $attentionItem->id)->update([
            'attention_code' => 'crop_check',
            'updated_at' => now(),
        ]);
        app(TrustedBatchReview::class)->decide(self::SESSION_ID, $reviewer, [(int) $firstItem->id], 'original');
    }

    private function imageBytes(int $theme): string
    {
        $width = 1400;
        $height = 1000;
        $image = imagecreatetruecolor($width, $height);
        if (! $image instanceof GdImage) {
            throw new RuntimeException('Unable to allocate an SG21 fictional image.');
        }

        $background = $this->color($image, 221, 215, 203);
        $frame = $this->color($image, 35 + ($theme * 4), 42 + ($theme * 3), 48 + ($theme * 2));
        $mat = $this->color($image, 244, 235, 215);
        $photo = $this->color($image, 82 + ($theme * 9), 112 + ($theme * 6), 124 + ($theme * 4));
        $light = $this->color($image, 250, 245, 230);
        $dark = $this->color($image, 30, 34, 39);
        $accent = $this->color($image, 123 + ($theme * 8), 63 + ($theme * 5), 72 + ($theme * 4));

        imagefilledrectangle($image, 0, 0, $width, $height, $background);
        imagefilledrectangle($image, 105, 75, 1295, 925, $frame);
        imagefilledrectangle($image, 150, 120, 1250, 880, $mat);
        imagefilledrectangle($image, 215, 175, 1185, 815, $photo);
        imagefilledellipse($image, 505, 420, 250, 300, $light);
        imagefilledellipse($image, 885, 410, 250, 300, $light);
        imagefilledellipse($image, 505, 420, 95, 125, $dark);
        imagefilledellipse($image, 885, 410, 95, 125, $dark);
        imagefilledrectangle($image, 380, 560, 630, 805, $accent);
        imagefilledrectangle($image, 760, 550, 1010, 805, $dark);
        imagestring($image, 5, 235, 195, 'FICTIONAL SG21 PHOTO '.str_pad((string) $theme, 2, '0', STR_PAD_LEFT), $light);
        imagestring($image, 5, 435, 845, 'SYNTHETIC REVIEW EVIDENCE', $dark);

        ob_start();
        $encoded = imagejpeg($image, null, 91);
        $bytes = ob_get_clean();
        unset($image);

        if (! $encoded || $bytes === '') {
            throw new RuntimeException('Unable to encode an SG21 fictional image.');
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
            throw new RuntimeException('Unable to allocate an SG21 fictional image colour.');
        }

        return $color;
    }
}
