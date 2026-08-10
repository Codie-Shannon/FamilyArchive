<?php

return static function (array $input): array {
    $sessionId = (string) ($input['session_id'] ?? '');
    $ownerEmail = (string) ($input['owner_email'] ?? '');
    $itemIds = array_values(array_unique(array_map('intval', $input['item_ids'] ?? [])));
    $allowedIdentificationIds = array_values(array_unique(array_map('intval', $input['allowed_identification_ids'] ?? [])));
    $maximumPixels = max(1, (int) ($input['maximum_source_pixels'] ?? 80000000));
    $expectedSources = [];
    foreach (($input['expected_sources'] ?? []) as $expectedSource) {
        $expectedSources[(int) ($expectedSource['item_id'] ?? 0)] = [
            'version_id' => (int) ($expectedSource['source_version_id'] ?? 0),
            'sha256' => strtolower((string) ($expectedSource['source_sha256'] ?? '')),
        ];
    }
    $actor = \App\Models\User::query()->where('email', $ownerEmail)->firstOrFail();
    $session = \Illuminate\Support\Facades\DB::table('cloud_import_sessions')
        ->where('session_id', $sessionId)
        ->firstOrFail();
    $derivatives = app(\App\Domain\Derivatives\Actions\GeneratePhotoViewingDerivatives::class);
    $approved = [];
    $failed = [];
    $skipped = [];

    foreach ($itemIds as $itemId) {
        $item = \Illuminate\Support\Facades\DB::table('cloud_import_items')
            ->where('cloud_import_session_id', (int) $session->id)
            ->where('id', $itemId)
            ->first();
        if (! $item || ! in_array($item->review_decision, ['hold', 'preserve_private'], true)) {
            $skipped[] = $itemId;

            continue;
        }
        if (\Illuminate\Support\Facades\DB::table('photo_split_proposals')
            ->where('cloud_import_item_id', $itemId)
            ->whereIn('state', ['suggested', 'ready'])
            ->exists()) {
            $skipped[] = $itemId;

            continue;
        }
        $sourceMetadata = json_decode((string) $item->source_metadata, true) ?: [];
        $classification = (string) ($sourceMetadata['content_safety']['classification'] ?? 'clear');
        if ($classification === 'identification_document'
            && ! in_array($itemId, $allowedIdentificationIds, true)) {
            $skipped[] = $itemId;

            continue;
        }
        if (! in_array($classification, ['clear', 'sensitive_minor_image', 'identification_document'], true)) {
            $skipped[] = $itemId;

            continue;
        }

        $promotion = \App\Domain\Archive\Models\ArchivePromotion::query()
            ->where('incoming_upload_id', $item->incoming_upload_id)
            ->first();
        if ($promotion) {
            $source = \App\Domain\Media\Models\MediaFileVersion::query()
                ->find($promotion->original_media_file_version_id);
        } else {
            $upload = \App\Domain\Intake\Models\IncomingUpload::query()->find($item->incoming_upload_id);
            $source = $upload === null ? null : \App\Domain\Media\Models\MediaFileVersion::query()
                ->where('version_type', 'original')
                ->where('sha256', $upload->sha256)
                ->orderBy('id')
                ->first();
        }
        $mediaItem = $source === null ? null : \App\Domain\Media\Models\MediaItem::query()->find($source->media_item_id);
        $expectedSource = $expectedSources[$itemId] ?? null;
        if (! $mediaItem || ! $source || ! $source->width || ! $source->height
            || ((int) $source->width * (int) $source->height) > $maximumPixels
            || ! is_array($expectedSource)
            || $expectedSource['version_id'] < 1
            || ! preg_match('/^[a-f0-9]{64}$/', $expectedSource['sha256'])
            || (int) $source->id !== $expectedSource['version_id']
            || ! hash_equals($expectedSource['sha256'], strtolower((string) $source->sha256))) {
            $failed[] = ['id' => $itemId, 'error' => 'Eligible immutable original is unavailable.'];
            \Illuminate\Support\Facades\DB::table('cloud_import_items')->where('id', $itemId)->update([
                'attention_code' => 'family_approval_failed',
                'updated_at' => now(),
            ]);

            continue;
        }

        $previous = [
            'review_status' => $mediaItem->getRawOriginal('review_status'),
            'visibility' => $mediaItem->getRawOriginal('visibility'),
            'approved_by' => $mediaItem->getRawOriginal('approved_by'),
            'approved_at' => $mediaItem->getRawOriginal('approved_at'),
        ];
        try {
            $mediaItem->forceFill([
                'review_status' => \App\Domain\Media\Enums\MediaReviewStatus::Approved,
                'visibility' => \App\Domain\Media\Enums\MediaVisibility::FamilyVisible,
                'approved_by' => $actor->id,
                'approved_at' => now(),
            ])->save();
            $derivatives->handle($mediaItem->fresh(), $actor);
            \Illuminate\Support\Facades\DB::transaction(function () use ($item, $itemId, $actor): void {
                \Illuminate\Support\Facades\DB::table('cloud_import_items')->where('id', $itemId)->update([
                    'review_decision' => 'original',
                    'attention_code' => in_array($item->attention_code, ['review_failed', 'exact_duplicate', 'preparation_failed'], true)
                        ? null
                        : $item->attention_code,
                    'reviewed_by' => $actor->id,
                    'reviewed_at' => now(),
                    'updated_at' => now(),
                ]);
                \Illuminate\Support\Facades\DB::table('contributor_submissions')
                    ->where('incoming_upload_id', $item->incoming_upload_id)
                    ->update([
                        'status' => 'accepted',
                        'reviewed_by' => $actor->id,
                        'reviewer_note' => 'Original accepted after owner-approved members-only family policy reconsideration.',
                        'reviewed_at' => now(),
                        'updated_at' => now(),
                    ]);
            });
            $approved[] = $itemId;
        } catch (Throwable $exception) {
            $mediaItem->refresh()->forceFill($previous)->save();
            \Illuminate\Support\Facades\DB::table('cloud_import_items')->where('id', $itemId)->update([
                'attention_code' => 'family_approval_failed',
                'updated_at' => now(),
            ]);
            $failed[] = [
                'id' => $itemId,
                'error' => mb_substr($exception->getMessage(), 0, 180),
            ];
        }
    }

    return ['approved' => $approved, 'failed' => $failed, 'skipped' => $skipped];
};
