<?php

namespace Database\Seeders;

use App\Domain\CloudImport\Services\HighVolumePhotoBatch;
use App\Domain\CloudImport\Services\TrustedBatchReview;
use App\Domain\Processing\Models\PhotoSplitProposal;
use App\Domain\Processing\Services\PhotoSplitReviewService;
use App\Models\User;
use GdImage;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

final class ScreenshotGroup34DemoSeeder extends Seeder
{
    private const SESSION_ID = '34000000-0000-4000-8000-000000000001';

    public function run(): void
    {
        abort_unless(app()->environment('local'), 403, 'The SG34 dataset is local-only.');

        $reviewer = User::query()->firstOrNew(['email' => 'sg34-reviewer@example.test']);
        $reviewer->forceFill([
            'name' => 'Riley Composite Review',
            'password' => Hash::make('SG34Demo!2026'),
            'email_verified_at' => now(),
            'role' => 'owner',
            'account_state' => 'approved',
            'family_connection' => 'Fictional SG34 evidence identity',
        ])->save();

        if (DB::table('cloud_import_sessions')->where('session_id', self::SESSION_ID)->exists()) {
            DB::table('cloud_import_sessions')->where('session_id', self::SESSION_ID)->update([
                'user_id' => $reviewer->id,
                'updated_at' => now(),
            ]);

            return;
        }

        $directory = storage_path('framework/testing/sg34-fictional-composites');
        File::deleteDirectory($directory);
        File::ensureDirectoryExists($directory);

        try {
            File::put($directory.'/fictional-four-photo-contact-sheet.jpg', $this->compositeBytes(true, 1));
            File::put($directory.'/fictional-borderless-four-photo-scan.jpg', $this->compositeBytes(false, 2));
            File::put($directory.'/fictional-manual-layout-source.jpg', $this->singleBytes());

            $planned = app(HighVolumePhotoBatch::class)->plan($reviewer, $directory, 25);
            DB::table('cloud_import_sessions')->where('session_id', $planned['session_id'])->update([
                'session_id' => self::SESSION_ID,
                'source_manifest' => json_encode([
                    'source_label' => 'Fictional composite-photo separation rehearsal',
                    'selected_count' => 3,
                    'paths_persisted' => false,
                    'approval_mode' => 'exception_first_batch_review',
                    'trust_level' => 'trusted_intake',
                    'evidence_scope' => 'synthetic-only',
                ], JSON_THROW_ON_ERROR),
            ]);
            app(HighVolumePhotoBatch::class)->process(self::SESSION_ID, $directory, 3);
        } finally {
            File::deleteDirectory($directory);
        }

        app(TrustedBatchReview::class)->prepare(self::SESSION_ID, $reviewer, 25);
        $sessionKey = DB::table('cloud_import_sessions')->where('session_id', self::SESSION_ID)->value('id');
        $items = DB::table('cloud_import_items')
            ->where('cloud_import_session_id', $sessionKey)
            ->orderBy('position')
            ->get();
        if ($items->count() !== 3) {
            throw new RuntimeException('The SG34 composite review batch was not prepared correctly.');
        }

        $splitService = app(PhotoSplitReviewService::class);
        foreach ($items->take(2) as $item) {
            $proposal = PhotoSplitProposal::query()->where('cloud_import_item_id', $item->id)->first();
            if (! $proposal instanceof PhotoSplitProposal || $proposal->regions()->count() !== 4) {
                throw new RuntimeException('An SG34 four-photo layout was not detected correctly.');
            }
        }

        $firstProposal = PhotoSplitProposal::query()->where('cloud_import_item_id', $items->first()->id)->firstOrFail();
        $splitService->saveRegions($firstProposal, $reviewer, $firstProposal->regions->map(fn ($region): array => [
            'region_id' => $region->region_id,
            'x' => $region->x_basis_points,
            'y' => $region->y_basis_points,
            'width' => $region->width_basis_points,
            'height' => $region->height_basis_points,
            'included' => true,
        ])->values()->all());

        $manualItem = $items->last();
        $manualProposal = $splitService->analyzeItem((int) $manualItem->id, $reviewer, true);
        if (! $manualProposal instanceof PhotoSplitProposal) {
            throw new RuntimeException('The SG34 manual override proposal was not created.');
        }
        DB::table('cloud_import_items')->where('id', $manualItem->id)->update([
            'attention_code' => 'manual_split_available',
            'updated_at' => now(),
        ]);
    }

    private function compositeBytes(bool $gutter, int $theme): string
    {
        $image = imagecreatetruecolor(1600, 1120);
        if (! $image instanceof GdImage) {
            throw new RuntimeException('Unable to allocate an SG34 composite image.');
        }

        $canvas = $this->color($image, 245, 241, 230);
        imagefilledrectangle($image, 0, 0, 1599, 1119, $canvas);
        $gap = $gutter ? 22 : 0;
        $regions = [
            [0, 0, 800 - intdiv($gap, 2), 560 - intdiv($gap, 2)],
            [800 + intdiv($gap, 2), 0, 1599, 560 - intdiv($gap, 2)],
            [0, 560 + intdiv($gap, 2), 800 - intdiv($gap, 2), 1119],
            [800 + intdiv($gap, 2), 560 + intdiv($gap, 2), 1599, 1119],
        ];
        $palettes = [
            [43 + ($theme * 4), 81, 104],
            [116, 68 + ($theme * 5), 72],
            [67, 103, 72 + ($theme * 5)],
            [105, 83, 128 + ($theme * 3)],
        ];

        foreach ($regions as $index => [$left, $top, $right, $bottom]) {
            $base = $this->color($image, ...$palettes[$index]);
            $light = $this->color($image, 237 - ($index * 4), 225 - ($index * 3), 197 + ($index * 5));
            $dark = $this->color($image, 25 + ($index * 5), 29 + ($index * 4), 35 + ($index * 3));
            imagefilledrectangle($image, $left, $top, $right, $bottom, $base);
            imagefilledellipse($image, $left + 235, $top + 215, 220, 270, $light);
            imagefilledellipse($image, $left + 550, $top + 225, 205, 255, $light);
            imagefilledellipse($image, $left + 235, $top + 210, 72, 98, $dark);
            imagefilledellipse($image, $left + 550, $top + 220, 68, 94, $dark);
            imagefilledrectangle($image, $left + 125, $top + 355, $left + 355, $bottom, $dark);
            imagefilledrectangle($image, $left + 445, $top + 360, $left + 665, $bottom, $light);
            imagestring($image, 5, $left + 24, $top + 24, 'FICTIONAL PHOTO '.($index + 1), $canvas);
        }

        return $this->jpeg($image);
    }

    private function singleBytes(): string
    {
        $image = imagecreatetruecolor(1600, 1120);
        if (! $image instanceof GdImage) {
            throw new RuntimeException('Unable to allocate an SG34 manual-layout image.');
        }
        $base = $this->color($image, 74, 101, 118);
        $light = $this->color($image, 233, 218, 188);
        $dark = $this->color($image, 30, 35, 41);
        imagefilledrectangle($image, 0, 0, 1599, 1119, $base);
        imagefilledellipse($image, 545, 410, 370, 440, $light);
        imagefilledellipse($image, 1060, 420, 350, 430, $light);
        imagefilledrectangle($image, 350, 680, 735, 1119, $dark);
        imagefilledrectangle($image, 875, 690, 1240, 1119, $light);
        imagestring($image, 5, 45, 45, 'FICTIONAL MANUAL SPLIT SOURCE', $light);

        return $this->jpeg($image);
    }

    private function jpeg(GdImage $image): string
    {
        ob_start();
        $encoded = imagejpeg($image, null, 92);
        $bytes = ob_get_clean();
        unset($image);
        if (! $encoded || $bytes === '') {
            throw new RuntimeException('Unable to encode an SG34 fictional image.');
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
            throw new RuntimeException('Unable to allocate an SG34 image colour.');
        }

        return $color;
    }
}
