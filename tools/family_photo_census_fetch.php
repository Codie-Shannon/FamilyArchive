<?php

return static function (array $input): array {
    $sessionId = (string) ($input['session_id'] ?? '');
    $after = max(0, (int) ($input['after'] ?? 0));
    $limit = max(1, min(500, (int) ($input['limit'] ?? 500)));
    $maximum = max(320, min(1200, (int) ($input['maximum_dimension'] ?? 720)));

    $session = \Illuminate\Support\Facades\DB::table('cloud_import_sessions')
        ->where('session_id', $sessionId)
        ->firstOrFail();
    $canonicalVersions = \Illuminate\Support\Facades\DB::table('media_file_versions')
        ->selectRaw('MIN(id) as id, sha256')
        ->where('version_type', 'original')
        ->groupBy('sha256');
    $items = \Illuminate\Support\Facades\DB::table('cloud_import_items as ci')
        ->join('incoming_uploads as iu', 'iu.id', '=', 'ci.incoming_upload_id')
        ->leftJoin('archive_promotions as ap', 'ap.incoming_upload_id', '=', 'ci.incoming_upload_id')
        ->leftJoinSub($canonicalVersions, 'canonical_index', static function ($join): void {
            $join->on('canonical_index.sha256', '=', 'iu.sha256');
        })
        ->leftJoin('media_file_versions as src', static function ($join): void {
            $join->on('src.id', '=', \Illuminate\Support\Facades\DB::raw('COALESCE(ap.original_media_file_version_id, canonical_index.id)'));
        })
        ->where('ci.cloud_import_session_id', $session->id)
        ->where('ci.id', '>', $after)
        ->orderBy('ci.id')
        ->limit($limit)
        ->get([
            'ci.id as item_id',
            'ci.position',
            'ci.review_decision',
            'ci.attention_code',
            'ci.source_metadata',
            'iu.incoming_path',
            'iu.sha256 as retained_sha256',
            'iu.file_size_bytes as retained_bytes',
            'ap.id as promotion_id',
            'src.id as source_version_id',
            'src.width',
            'src.height',
        ]);

    $rows = [];
    foreach ($items as $item) {
        try {
            if ($item->source_version_id === null) {
                throw new RuntimeException('No canonical original version represents this retained source.');
            }
            // Keep the remote command bounded: it returns only short-lived private
            // object URLs and metadata. The local worker downloads in parallel,
            // verifies every byte count and SHA, then performs reduced-scale decode.
            $sourceDownloadUrl = \Illuminate\Support\Facades\Storage::disk('archive_quarantine')
                ->temporaryUrl($item->incoming_path, now()->addMinutes(15));

            $proposal = \App\Domain\Processing\Models\PhotoSplitProposal::with('regions')
                ->where('cloud_import_item_id', $item->item_id)
                ->first();
            $proposalData = null;
            if ($proposal) {
                $proposalData = [
                    'id' => $proposal->id,
                    'state' => $proposal->state,
                    'confidence' => (float) $proposal->confidence,
                    'method' => $proposal->detection_method,
                    'regions' => $proposal->regions->map(static fn ($region): array => [
                        'region_id' => $region->region_id,
                        'x' => (int) $region->x_basis_points,
                        'y' => (int) $region->y_basis_points,
                        'width' => (int) $region->width_basis_points,
                        'height' => (int) $region->height_basis_points,
                        'rotation_degrees' => (int) $region->rotation_degrees,
                        'confidence' => (float) $region->confidence,
                        'review_state' => $region->review_state,
                    ])->values(),
                ];
            }

            $sourceMetadata = json_decode((string) $item->source_metadata, true) ?: [];
            $rows[] = [
                'ok' => true,
                'item_id' => (int) $item->item_id,
                'position' => (int) $item->position,
                'review_decision' => $item->review_decision,
                'attention_code' => $item->attention_code,
                'source_version_id' => (int) $item->source_version_id,
                'source_sha256' => (string) $item->retained_sha256,
                'source_bytes' => (int) $item->retained_bytes,
                'canonical_duplicate' => $item->promotion_id === null,
                'width' => (int) $item->width,
                'height' => (int) $item->height,
                'content_classification' => (string) ($sourceMetadata['content_safety']['classification'] ?? 'clear'),
                'split_proposal' => $proposalData,
                'thumbnail_sha256' => null,
                'thumbnail_base64' => null,
                'local_thumbnail_required' => true,
                'source_download_url' => $sourceDownloadUrl,
            ];
        } catch (Throwable $exception) {
            $rows[] = [
                'ok' => false,
                'item_id' => (int) $item->item_id,
                'position' => (int) $item->position,
                'error' => mb_substr($exception->getMessage(), 0, 180),
            ];
        }
    }

    return $rows;
};
