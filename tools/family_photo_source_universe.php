<?php

use Illuminate\Support\Facades\DB;

return static function (array $input): array {
    $sessionId = (string) ($input['session_id'] ?? '');
    $session = DB::table('cloud_import_sessions')->where('session_id', $sessionId)->firstOrFail();
    $sessionDatabaseId = (int) $session->id;

    $items = DB::table('cloud_import_items as ci')
        ->join('incoming_uploads as iu', 'iu.id', '=', 'ci.incoming_upload_id')
        ->leftJoin('archive_promotions as ap', 'ap.incoming_upload_id', '=', 'ci.incoming_upload_id')
        ->where('ci.cloud_import_session_id', $sessionDatabaseId)
        ->where('ci.media_type', 'photo')
        ->orderBy('ci.position')
        ->get([
            'ci.id',
            'ci.position',
            'ci.attention_code',
            'ci.review_decision',
            'ci.incoming_upload_id',
            'iu.sha256',
            'iu.file_size_bytes',
            'ap.id as promotion_id',
        ]);

    $missing = [];
    $promotedCount = 0;
    $unrepresentedCount = 0;
    $canonicalOutsideSessionCount = 0;
    foreach ($items as $item) {
        if ($item->promotion_id !== null) {
            $promotedCount++;

            continue;
        }

        $versionIds = DB::table('media_file_versions')
            ->where('version_type', 'original')
            ->where('sha256', (string) $item->sha256)
            ->orderBy('id')
            ->pluck('id')
            ->map(static fn ($id): int => (int) $id)
            ->all();
        $canonicalInSession = $versionIds === [] ? 0 : DB::table('archive_promotions as canonical_ap')
            ->join('cloud_import_items as canonical_ci', 'canonical_ci.incoming_upload_id', '=', 'canonical_ap.incoming_upload_id')
            ->where('canonical_ci.cloud_import_session_id', $sessionDatabaseId)
            ->whereIn('canonical_ap.original_media_file_version_id', $versionIds)
            ->distinct()
            ->count('canonical_ci.id');
        if ($versionIds === []) {
            $unrepresentedCount++;
        } elseif ($canonicalInSession < 1) {
            $canonicalOutsideSessionCount++;
        }
        $missing[] = [
            'item_id' => (int) $item->id,
            'position' => (int) $item->position,
            'incoming_upload_id' => (int) $item->incoming_upload_id,
            'attention_code' => (string) $item->attention_code,
            'review_decision' => (string) $item->review_decision,
            'sha256' => (string) $item->sha256,
            'bytes' => (int) $item->file_size_bytes,
            'canonical_original_version_ids' => $versionIds,
            'canonical_in_session_count' => $canonicalInSession,
        ];
    }

    $sessionTotal = $items->count();
    $duplicateRepresentedCount = count($missing) - $unrepresentedCount;
    $facts = [
        'session_id' => $sessionId,
        'session_total' => $sessionTotal,
        'promoted_source_count' => $promotedCount,
        'duplicate_represented_count' => $duplicateRepresentedCount,
        'unrepresented_count' => $unrepresentedCount,
        'canonical_outside_session_count' => $canonicalOutsideSessionCount,
        'accounted_total' => $promotedCount + $duplicateRepresentedCount + $unrepresentedCount,
        'missing_items' => $missing,
    ];

    return [
        'status' => $unrepresentedCount === 0
            && $facts['accounted_total'] === $sessionTotal
                ? 'complete'
                : 'needs_attention',
        ...$facts,
        'fingerprint_sha256' => hash('sha256', json_encode($facts, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES)),
    ];
};
