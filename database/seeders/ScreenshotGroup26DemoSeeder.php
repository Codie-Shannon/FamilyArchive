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

final class ScreenshotGroup26DemoSeeder extends Seeder
{
    private const SESSION_ID = '26000000-0000-4000-8000-000000000001';

    public function run(): void
    {
        abort_unless(app()->environment('local'), 403, 'The SG26 dataset is local-only.');

        $reviewer = User::query()->firstOrNew(['email' => 'sg26-reviewer@example.test']);
        $reviewer->forceFill([
            'name' => 'Mereana Cole',
            'password' => Hash::make('SG26Demo!2026'),
            'email_verified_at' => now(),
            'role' => 'trusted_contributor',
            'account_state' => 'approved',
            'family_connection' => 'Fictional SG26 evidence identity.',
        ])->save();

        if (DB::table('cloud_import_sessions')->where('session_id', self::SESSION_ID)->exists()) {
            DB::table('cloud_import_sessions')->where('session_id', self::SESSION_ID)->update([
                'user_id' => $reviewer->id,
                'updated_at' => now(),
            ]);

            return;
        }

        $directory = storage_path('framework/testing/sg26-fictional-editor-batch');
        File::deleteDirectory($directory);
        File::ensureDirectoryExists($directory);

        try {
            foreach (range(1, 4) as $theme) {
                File::put(
                    $directory.'/fictional-original-'.str_pad((string) $theme, 2, '0', STR_PAD_LEFT).'.jpg',
                    $this->imageBytes($theme),
                );
            }

            $planned = app(HighVolumePhotoBatch::class)->plan($reviewer, $directory, 25);
            DB::table('cloud_import_sessions')->where('session_id', $planned['session_id'])->update([
                'session_id' => self::SESSION_ID,
                'source_manifest' => json_encode([
                    'source_label' => 'Fictional original-first restoration rehearsal',
                    'selected_count' => 4,
                    'paths_persisted' => false,
                    'approval_mode' => 'original_first_manual_editor',
                    'trust_level' => 'trusted_intake',
                    'evidence_scope' => 'synthetic-only',
                ], JSON_THROW_ON_ERROR),
            ]);
            app(HighVolumePhotoBatch::class)->process(self::SESSION_ID, $directory, 4);
        } finally {
            File::deleteDirectory($directory);
        }

        app(TrustedBatchReview::class)->prepare(self::SESSION_ID, $reviewer, 25);
        $sessionKey = DB::table('cloud_import_sessions')->where('session_id', self::SESSION_ID)->value('id');
        $manualOnlyItem = DB::table('cloud_import_items')
            ->where('cloud_import_session_id', $sessionKey)
            ->where('position', 4)
            ->first();
        if ($manualOnlyItem === null) {
            throw new RuntimeException('The SG26 manual-only item was not prepared correctly.');
        }

        DB::table('cloud_import_items')->where('id', $manualOnlyItem->id)->update([
            'restoration_candidate_id' => null,
            'attention_code' => 'manual_edit',
            'updated_at' => now(),
        ]);
        DB::table('cloud_import_sessions')->where('id', $sessionKey)->update([
            'attention_count' => 1,
            'review_state' => 'needs_attention',
            'updated_at' => now(),
        ]);
    }

    private function imageBytes(int $theme): string
    {
        $image = imagecreatetruecolor(1400, 1000);
        if (! $image instanceof GdImage) {
            throw new RuntimeException('Unable to allocate an SG26 fictional image.');
        }

        $background = $this->color($image, 224, 216, 199);
        $frame = $this->color($image, 47 + ($theme * 5), 42 + ($theme * 3), 38 + ($theme * 2));
        $mat = $this->color($image, 244, 234, 211);
        $photo = $this->color($image, 75 + ($theme * 8), 103 + ($theme * 7), 114 + ($theme * 6));
        $light = $this->color($image, 249, 242, 223);
        $dark = $this->color($image, 29, 34, 38);
        $accent = $this->color($image, 130 + ($theme * 5), 64 + ($theme * 4), 73 + ($theme * 3));

        imagefilledrectangle($image, 0, 0, 1400, 1000, $background);
        imagefilledrectangle($image, 85, 65, 1320, 935, $frame);
        imagefilledrectangle($image, 130, 110, 1275, 890, $mat);
        imagefilledrectangle($image, 200, 170, 1205, 820, $photo);
        imagefilledellipse($image, 500, 415, 255, 305, $light);
        imagefilledellipse($image, 890, 405, 255, 305, $light);
        imagefilledellipse($image, 500, 415, 95, 125, $dark);
        imagefilledellipse($image, 890, 405, 95, 125, $dark);
        imagefilledrectangle($image, 370, 560, 635, 820, $accent);
        imagefilledrectangle($image, 755, 550, 1020, 820, $dark);
        imagestring($image, 5, 225, 190, 'FICTIONAL SG26 ORIGINAL '.str_pad((string) $theme, 2, '0', STR_PAD_LEFT), $light);
        imagestring($image, 5, 420, 850, 'SYNTHETIC EDITOR EVIDENCE', $dark);

        ob_start();
        $encoded = imagejpeg($image, null, 91);
        $bytes = ob_get_clean();
        unset($image);

        if (! $encoded || $bytes === '') {
            throw new RuntimeException('Unable to encode an SG26 fictional image.');
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
            throw new RuntimeException('Unable to allocate an SG26 fictional image colour.');
        }

        return $color;
    }
}
