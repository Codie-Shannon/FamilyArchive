<?php

namespace Database\Seeders;

use App\Domain\Processing\Models\PhotoSplitProposal;
use App\Domain\Processing\Models\PhotoSplitRegion;
use App\Domain\Processing\Services\PhotoSplitReviewService;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class ScreenshotGroup36DemoSeeder extends Seeder
{
    private const SESSION_ID = '34000000-0000-4000-8000-000000000001';

    public function run(): void
    {
        abort_unless(app()->environment('local'), 403, 'The SG36 dataset is local-only.');

        $this->call(ScreenshotGroup34DemoSeeder::class);

        $reviewer = User::query()->where('email', 'sg34-reviewer@example.test')->first();
        if (! $reviewer instanceof User) {
            throw new RuntimeException('The SG36 review identity was not prepared.');
        }

        $sessionKey = DB::table('cloud_import_sessions')
            ->where('session_id', self::SESSION_ID)
            ->value('id');
        $itemIds = DB::table('cloud_import_items')
            ->where('cloud_import_session_id', $sessionKey)
            ->orderBy('position')
            ->limit(2)
            ->pluck('id');
        if ($itemIds->count() !== 2) {
            throw new RuntimeException('The SG36 composite sources were not prepared.');
        }

        $service = app(PhotoSplitReviewService::class);
        foreach ($itemIds->values() as $proposalIndex => $itemId) {
            $proposal = PhotoSplitProposal::query()
                ->with('regions')
                ->where('cloud_import_item_id', $itemId)
                ->first();
            if (! $proposal instanceof PhotoSplitProposal || $proposal->regions->count() !== 4) {
                throw new RuntimeException('An SG36 four-photo proposal was not prepared.');
            }

            $regions = $proposal->regions->map(
                fn (PhotoSplitRegion $region): array => [
                    'region_id' => $region->region_id,
                    'x' => $region->x_basis_points,
                    'y' => $region->y_basis_points,
                    'width' => $region->width_basis_points,
                    'height' => $region->height_basis_points,
                    'rotation_degrees' => match (true) {
                        $proposalIndex === 0 && $region->position === 1 => 90,
                        $proposalIndex === 1 && $region->position === 3 => 270,
                        default => 0,
                    },
                    'included' => true,
                ],
            )->values()->all();

            $service->saveRegions($proposal, $reviewer, $regions);
        }

        DB::table('cloud_import_sessions')->where('session_id', self::SESSION_ID)->update([
            'source_manifest' => json_encode([
                'source_label' => 'Fictional clipping-safe composite-photo rehearsal',
                'selected_count' => 3,
                'paths_persisted' => false,
                'processing_order' => ['padded_extract', 'independent_rotate', 'final_edge_crop'],
                'evidence_scope' => 'synthetic-only',
            ], JSON_THROW_ON_ERROR),
            'updated_at' => now(),
        ]);
    }
}
