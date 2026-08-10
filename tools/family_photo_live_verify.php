<?php

return static function (array $input): array {
    $sessionId = (string) ($input['session_id'] ?? '');
    $afterId = max(0, (int) ($input['after_id'] ?? 0));
    $limit = max(1, min(100, (int) ($input['limit'] ?? 10)));
    $session = \Illuminate\Support\Facades\DB::table('cloud_import_sessions')
        ->where('session_id', $sessionId)
        ->firstOrFail();

    $promotedOriginals = \Illuminate\Support\Facades\DB::table('cloud_import_items as ci')
        ->join('archive_promotions as ap', 'ap.incoming_upload_id', '=', 'ci.incoming_upload_id')
        ->where('ci.cloud_import_session_id', $session->id)
        ->where('ci.review_decision', 'original')
        ->whereNotNull('ap.media_item_id')
        ->pluck('ap.media_item_id');
    $canonicalIndex = \Illuminate\Support\Facades\DB::table('media_file_versions')
        ->selectRaw('MIN(id) as id, sha256')
        ->where('version_type', 'original')
        ->groupBy('sha256');
    $canonicalOriginals = \Illuminate\Support\Facades\DB::table('cloud_import_items as ci')
        ->join('incoming_uploads as iu', 'iu.id', '=', 'ci.incoming_upload_id')
        ->leftJoin('archive_promotions as ap', 'ap.incoming_upload_id', '=', 'ci.incoming_upload_id')
        ->joinSub($canonicalIndex, 'canonical_index', fn ($join) => $join->on('canonical_index.sha256', '=', 'iu.sha256'))
        ->join('media_file_versions as version', 'version.id', '=', 'canonical_index.id')
        ->where('ci.cloud_import_session_id', $session->id)
        ->whereNull('ap.id')
        ->where('ci.review_decision', 'original')
        ->pluck('version.media_item_id');
    $splitOutputs = \Illuminate\Support\Facades\DB::table('photo_split_regions as region')
        ->join('photo_split_proposals as proposal', 'proposal.id', '=', 'region.photo_split_proposal_id')
        ->join('cloud_import_items as ci', 'ci.id', '=', 'proposal.cloud_import_item_id')
        ->join('media_items as output', 'output.id', '=', 'region.output_media_item_id')
        ->where('ci.cloud_import_session_id', $session->id)
        ->where('proposal.state', 'published')
        ->where('region.review_state', 'included')
        ->where('output.review_status', 'approved')
        ->where('output.visibility', 'family_visible')
        ->pluck('region.output_media_item_id');
    $ids = $promotedOriginals
        ->merge($canonicalOriginals)
        ->merge($splitOutputs)
        ->unique()
        ->filter(fn ($id): bool => (int) $id > $afterId)
        ->sort()
        ->take($limit)
        ->values();

    $viewer = \App\Models\User::query()
        ->where('account_state', 'approved')
        ->whereIn('role', ['viewer', 'contributor', 'trusted_contributor'])
        ->orderBy('id')
        ->first();
    if (! $viewer) {
        throw new RuntimeException('No approved non-administrator family member is available for verification.');
    }

    $access = app(\App\Domain\Access\Services\ArchiveAccess::class);
    $sources = app(\App\Domain\Derivatives\Services\ApprovedPhotoViewingSource::class);
    $gallery = app(\App\Domain\Browsing\Queries\ApprovedPhotoGalleryQuery::class);
    $details = app(\App\Domain\Browsing\Queries\ApprovedPhotoDetailQuery::class);
    $verified = [];
    $failed = [];

    $verifyObject = static function (\App\Domain\Media\Models\MediaFileVersion $version, string $label): void {
        $disk = \Illuminate\Support\Facades\Storage::disk($version->storage_disk);
        if (! $disk->exists($version->storage_path)) {
            throw new RuntimeException("{$label} object is unavailable.");
        }
        $bytes = $disk->get($version->storage_path);
        if (strlen($bytes) !== (int) $version->file_size_bytes
            || ! hash_equals(strtolower((string) $version->sha256), hash('sha256', $bytes))) {
            throw new RuntimeException("{$label} object failed size or SHA verification.");
        }
    };

    foreach ($ids as $id) {
        try {
            $item = \App\Domain\Media\Models\MediaItem::query()->findOrFail($id);
            if ($item->review_status !== \App\Domain\Media\Enums\MediaReviewStatus::Approved
                || $item->visibility !== \App\Domain\Media\Enums\MediaVisibility::FamilyVisible) {
                throw new RuntimeException('Item is not approved and family-visible.');
            }
            $source = $sources->resolve($item);
            if (! $source) {
                throw new RuntimeException('No approved viewing source exists.');
            }
            $verifyObject($source, 'Viewing source');

            $thumbnail = $item->fileVersions()
                ->where('version_type', 'thumbnail')
                ->where('generation_status', 'ready')
                ->where('is_preferred', true)
                ->where('parent_version_id', $source->id)
                ->first();
            if (! $thumbnail) {
                throw new RuntimeException('Preferred thumbnail is unavailable.');
            }
            $verifyObject($thumbnail, 'Thumbnail');

            $web = $item->fileVersions()
                ->where('version_type', 'web_display')
                ->where('generation_status', 'ready')
                ->where('is_preferred', true)
                ->where('parent_version_id', $source->id)
                ->first();
            if (! $web) {
                throw new RuntimeException('Preferred web-display derivative is unavailable.');
            }
            $verifyObject($web, 'Web-display');

            if (! $access->canView($viewer, $item)) {
                throw new RuntimeException('Approved family member cannot access the item.');
            }
            $page = $gallery->handle($viewer, 1, (int) $id);
            $row = $page->items()[0] ?? null;
            if (! $row || (int) $row->mediaItemId !== (int) $id || $row->thumbnailStatus !== 'ready') {
                throw new RuntimeException('Authenticated gallery did not return this exact ready photo.');
            }
            $detail = $details->handle($viewer, $item);
            if (! $detail
                || (int) $detail->mediaItemId !== (int) $id
                || $detail->webDisplayStatus !== 'ready'
                || $detail->thumbnailStatus !== 'ready') {
                throw new RuntimeException('Authenticated detail query did not return this exact ready photo.');
            }
            $verified[] = (int) $id;
        } catch (Throwable $exception) {
            $failed[] = [
                'id' => (int) $id,
                'error' => mb_substr($exception->getMessage(), 0, 180),
            ];
        }
    }

    return ['ids' => $ids->map(fn ($id): int => (int) $id)->all(), 'verified' => $verified, 'failed' => $failed];
};
