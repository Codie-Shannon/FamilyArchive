<?php

return static function (array $input): array {
    $sessionId = (string) ($input['session_id'] ?? '');
    $ownerEmail = (string) ($input['owner_email'] ?? '');
    $itemId = max(0, (int) ($input['item_id'] ?? 0));
    $regions = array_values($input['regions'] ?? []);
    $evidence = (string) ($input['evidence'] ?? '');
    $requestedFamilyVisibility = (bool) ($input['make_family_visible'] ?? true);
    $allowedIdentificationIds = array_values(array_unique(array_map('intval', $input['allowed_identification_ids'] ?? [])));
    $expectedSourceVersionId = max(0, (int) ($input['expected_source_version_id'] ?? 0));
    $expectedSourceSha256 = strtolower((string) ($input['expected_source_sha256'] ?? ''));

    $actor = \App\Models\User::query()->where('email', $ownerEmail)->firstOrFail();
    $session = \Illuminate\Support\Facades\DB::table('cloud_import_sessions')
        ->where('session_id', $sessionId)
        ->firstOrFail();
    $item = \Illuminate\Support\Facades\DB::table('cloud_import_items')
        ->where('cloud_import_session_id', (int) $session->id)
        ->where('id', $itemId)
        ->firstOrFail();
    $sourceMetadata = json_decode((string) $item->source_metadata, true) ?: [];
    $classification = (string) ($sourceMetadata['content_safety']['classification'] ?? 'clear');
    $eligibleForFamily = in_array($classification, ['clear', 'sensitive_minor_image'], true)
        || ($classification === 'identification_document' && in_array($itemId, $allowedIdentificationIds, true));
    $makeFamilyVisible = $requestedFamilyVisibility && $eligibleForFamily;

    $promotion = \App\Domain\Archive\Models\ArchivePromotion::query()
        ->where('incoming_upload_id', $item->incoming_upload_id)
        ->first();
    if ($promotion) {
        $source = \App\Domain\Media\Models\MediaFileVersion::query()
            ->findOrFail($promotion->original_media_file_version_id);
    } else {
        $upload = \App\Domain\Intake\Models\IncomingUpload::query()
            ->findOrFail($item->incoming_upload_id);
        $source = \App\Domain\Media\Models\MediaFileVersion::query()
            ->where('version_type', 'original')
            ->where('sha256', $upload->sha256)
            ->orderBy('id')
            ->firstOrFail();
    }
    if ($expectedSourceVersionId < 1
        || ! preg_match('/^[a-f0-9]{64}$/', $expectedSourceSha256)
        || (int) $source->id !== $expectedSourceVersionId
        || ! hash_equals($expectedSourceSha256, strtolower((string) $source->sha256))) {
        throw new RuntimeException('The reviewed split decision no longer matches its immutable census source.');
    }
    $sourceItem = \App\Domain\Media\Models\MediaItem::query()->find($source->media_item_id);
    $sourceState = $sourceItem === null ? null : [
        'review_status' => $sourceItem->getRawOriginal('review_status'),
        'visibility' => $sourceItem->getRawOriginal('visibility'),
        'approved_by' => $sourceItem->getRawOriginal('approved_by'),
        'approved_at' => $sourceItem->getRawOriginal('approved_at'),
    ];
    $itemState = [
        'review_decision' => $item->review_decision,
        'attention_code' => $item->attention_code,
        'reviewed_by' => $item->reviewed_by,
        'reviewed_at' => $item->reviewed_at,
    ];

    $proposal = \App\Domain\Processing\Models\PhotoSplitProposal::query()
        ->where('cloud_import_item_id', $itemId)
        ->first();
    if (! $proposal) {
        $proposal = \App\Domain\Processing\Models\PhotoSplitProposal::query()->create([
            'cloud_import_item_id' => $itemId,
            'source_version_id' => $source->id,
            'created_by' => $actor->id,
            'state' => 'suggested',
            'confidence' => 1,
            'detection_method' => 'codex_visual_census_v1',
            'analysis' => [
                'detected' => true,
                'regions' => $regions,
                'review_evidence' => $evidence,
            ],
        ]);
    }
    if ((int) $proposal->source_version_id !== (int) $source->id) {
        throw new RuntimeException('The split proposal is bound to a different immutable source.');
    }

    if ($proposal->state !== 'published') {
        $proposal->forceFill([
            'state' => 'suggested',
            'detection_method' => 'codex_visual_census_v1',
            'analysis' => [
                'detected' => true,
                'regions' => $regions,
                'review_evidence' => $evidence,
            ],
        ])->save();
        $service = app(\App\Domain\Processing\Services\PhotoSplitReviewService::class);
        $proposal = $service->saveRegions($proposal, $actor, $regions);
        $visibility = $makeFamilyVisible
            ? \App\Domain\Media\Enums\MediaVisibility::FamilyVisible
            : \App\Domain\Media\Enums\MediaVisibility::PrivateArchive;
        $children = $service->publish($proposal, $actor, $visibility);
    } else {
        $children = $proposal->regions()
            ->where('review_state', 'included')
            ->whereNotNull('output_media_item_id')
            ->with('outputMediaItem')
            ->get()
            ->pluck('outputMediaItem')
            ->filter()
            ->values();
    }

    $verified = [];
    foreach ($children as $child) {
        $visibility = $makeFamilyVisible
            ? \App\Domain\Media\Enums\MediaVisibility::FamilyVisible
            : \App\Domain\Media\Enums\MediaVisibility::PrivateArchive;
        $child->forceFill([
            'review_status' => \App\Domain\Media\Enums\MediaReviewStatus::Approved,
            'visibility' => $visibility,
            'approved_by' => $actor->id,
            'approved_at' => $child->approved_at ?? now(),
        ])->save();
        $view = app(\App\Domain\Derivatives\Services\ApprovedPhotoViewingSource::class)->resolve($child);
        if (! $view) {
            throw new RuntimeException('Split viewing source missing.');
        }
        $viewBytes = \Illuminate\Support\Facades\Storage::disk($view->storage_disk)->get($view->storage_path);
        if (strlen($viewBytes) !== (int) $view->file_size_bytes
            || ! hash_equals(strtolower((string) $view->sha256), hash('sha256', $viewBytes))) {
            throw new RuntimeException('Split viewing object unavailable or digest-mismatched.');
        }
        $thumbnail = $child->fileVersions()
            ->where('version_type', 'thumbnail')
            ->where('generation_status', 'ready')
            ->where('is_preferred', true)
            ->where('parent_version_id', $view->id)
            ->first();
        if (! $thumbnail) {
            throw new RuntimeException('Split thumbnail record unavailable.');
        }
        $thumbnailBytes = \Illuminate\Support\Facades\Storage::disk($thumbnail->storage_disk)->get($thumbnail->storage_path);
        if (strlen($thumbnailBytes) !== (int) $thumbnail->file_size_bytes
            || ! hash_equals(strtolower((string) $thumbnail->sha256), hash('sha256', $thumbnailBytes))) {
            throw new RuntimeException('Split thumbnail unavailable or digest-mismatched.');
        }
        $verified[] = (int) $child->id;
    }

    if (! $requestedFamilyVisibility) {
        if ($sourceItem && $sourceState) {
            $sourceItem->refresh()->forceFill($sourceState)->save();
        }
        \Illuminate\Support\Facades\DB::table('cloud_import_items')->where('id', $itemId)->update([
            ...$itemState,
            'updated_at' => now(),
        ]);
    } elseif ($makeFamilyVisible) {
        if ($sourceItem) {
            $sourceItem->forceFill([
                'review_status' => \App\Domain\Media\Enums\MediaReviewStatus::Hidden,
                'approved_by' => null,
                'approved_at' => null,
            ])->save();
        }
        \Illuminate\Support\Facades\DB::table('cloud_import_items')->where('id', $itemId)->update([
            'review_decision' => 'split_photos',
            'attention_code' => null,
            'reviewed_by' => $actor->id,
            'reviewed_at' => now(),
            'updated_at' => now(),
        ]);
        \Illuminate\Support\Facades\DB::table('contributor_submissions')
            ->where('incoming_upload_id', $item->incoming_upload_id)
            ->update([
                'status' => 'accepted',
                'reviewed_by' => $actor->id,
                'reviewer_note' => 'Multi-photo source preserved and split after crop verification.',
                'reviewed_at' => now(),
                'updated_at' => now(),
            ]);
    } else {
        if ($sourceItem) {
            $sourceItem->forceFill([
                'review_status' => \App\Domain\Media\Enums\MediaReviewStatus::Hidden,
                'approved_by' => null,
                'approved_at' => null,
            ])->save();
        }
        \Illuminate\Support\Facades\DB::table('cloud_import_items')->where('id', $itemId)->update([
            ...$itemState,
            'updated_at' => now(),
        ]);
    }

    return [
        'source_item_id' => $itemId,
        'outputs' => $verified,
        'classification' => $classification,
        'eligible_for_family' => $eligibleForFamily,
        'family_visible' => $makeFamilyVisible,
    ];
};
