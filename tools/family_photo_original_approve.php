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
            'rotation_degrees' => (int) ($expectedSource['rotation_degrees'] ?? 0),
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

    $prepareRotation = static function (
        object $session,
        object $item,
        \App\Domain\Media\Models\MediaItem $mediaItem,
        \App\Domain\Media\Models\MediaFileVersion $source,
        \App\Models\User $actor,
        int $clockwiseDegrees,
    ): \App\Domain\Media\Models\MediaFileVersion {
        if (! in_array($clockwiseDegrees, [-90, 90, 180], true)) {
            throw new RuntimeException('The reviewed whole-photo rotation is invalid.');
        }

        $matching = \App\Domain\Media\Models\MediaFileVersion::query()
            ->with('restorationCandidate')
            ->where('media_item_id', $mediaItem->id)
            ->where('parent_version_id', $source->id)
            ->where('version_type', \App\Domain\Media\Enums\MediaFileVersionType::EditedFull)
            ->where('generation_status', \App\Domain\Media\Enums\GenerationStatus::Ready)
            ->get()
            ->first(static function ($version) use ($source, $clockwiseDegrees): bool {
                $recipe = $version->generation_recipe;

                return $version->restorationCandidate?->review_state === 'approved'
                    && is_array($recipe)
                    && ($recipe['operation'] ?? null) === 'family_photo_single_rotation'
                    && ($recipe['source_sha256'] ?? null) === strtolower((string) $source->sha256)
                    && (int) ($recipe['clockwise_degrees'] ?? 0) === $clockwiseDegrees;
            });
        if ($matching instanceof \App\Domain\Media\Models\MediaFileVersion) {
            $disk = \Illuminate\Support\Facades\Storage::disk($matching->storage_disk);
            $bytes = $disk->get($matching->storage_path);
            if (strlen($bytes) !== (int) $matching->file_size_bytes
                || ! hash_equals(strtolower((string) $matching->sha256), hash('sha256', $bytes))) {
                throw new RuntimeException('The reviewed rotation candidate failed object integrity verification.');
            }
            \App\Domain\Media\Models\MediaFileVersion::query()
                ->where('media_item_id', $mediaItem->id)
                ->where('version_type', \App\Domain\Media\Enums\MediaFileVersionType::EditedFull)
                ->where('id', '!=', $matching->id)
                ->update(['is_preferred' => false]);
            $matching->forceFill(['is_preferred' => true])->save();

            return $matching;
        }

        $sourceDisk = \Illuminate\Support\Facades\Storage::disk($source->storage_disk);
        $sourceBytes = $sourceDisk->get($source->storage_path);
        if (strlen($sourceBytes) !== (int) $source->file_size_bytes
            || ! hash_equals(strtolower((string) $source->sha256), hash('sha256', $sourceBytes))) {
            throw new RuntimeException('The immutable source failed rotation-input integrity verification.');
        }

        $editor = app(\App\Domain\Processing\Services\ManualRestorationEditor::class);
        $render = new ReflectionMethod($editor, 'renderWithProcessingMemory');
        $settings = [
            'orient' => true,
            'quarter_turn' => intdiv($clockwiseDegrees, 90),
            'straighten' => 0,
            'crop_left' => 0,
            'crop_top' => 0,
            'crop_right' => 0,
            'crop_bottom' => 0,
            'brightness' => 0,
            'contrast' => 0,
            'red' => 0,
            'green' => 0,
            'blue' => 0,
            'denoise' => 0,
            'sharpen' => 0,
            'cleanup' => 0,
        ];
        [$candidateBytes, $width, $height, $operations] = $render->invoke(
            $editor,
            $sourceBytes,
            (string) $source->mime_type,
            $settings,
        );
        if (! isset($operations['rotate'])) {
            throw new RuntimeException('The reviewed whole-photo rotation was not rendered.');
        }

        $writer = app(\App\Domain\Derivatives\Contracts\NoOverwriteDerivativeWriter::class);
        $path = app(\App\Domain\Archive\Services\StoragePathValidator::class)->validateRelativePath(
            'restoration-candidates/'.$source->media_item_id.'/'.(string) \Illuminate\Support\Str::uuid().'.webp',
        );
        $written = $writer->write($path, $candidateBytes);
        try {
            $version = \Illuminate\Support\Facades\DB::transaction(static function () use (
                $session,
                $item,
                $mediaItem,
                $source,
                $actor,
                $clockwiseDegrees,
                $operations,
                $written,
                $width,
                $height,
            ): \App\Domain\Media\Models\MediaFileVersion {
                $lockedItem = \Illuminate\Support\Facades\DB::table('cloud_import_items')
                    ->where('cloud_import_session_id', (int) $session->id)
                    ->where('id', (int) $item->id)
                    ->lockForUpdate()
                    ->firstOrFail();
                if (! in_array($lockedItem->review_decision, ['hold', 'preserve_private'], true)) {
                    throw new RuntimeException('The photo review state changed before rotation publication.');
                }
                $lockedSource = \App\Domain\Media\Models\MediaFileVersion::query()->lockForUpdate()->findOrFail($source->id);
                if (! hash_equals(strtolower((string) $source->sha256), strtolower((string) $lockedSource->sha256))) {
                    throw new RuntimeException('The immutable source changed before rotation publication.');
                }

                $recipe = \App\Domain\Processing\Models\ProcessingRecipe::query()->create([
                    'created_by' => $actor->id,
                    'recipe_id' => 'RCP-'.strtoupper(\Illuminate\Support\Str::random(12)),
                    'name' => 'Family photo reviewed rotation '.(int) $item->id,
                    'version' => 1,
                    'operations' => $operations,
                    'automation_source' => 'family_photo_review',
                    'is_batch_profile' => false,
                    'is_active' => true,
                ]);
                $job = \App\Domain\Processing\Models\ProcessingJob::query()->create([
                    'job_id' => (string) \Illuminate\Support\Str::uuid(),
                    'media_item_id' => $mediaItem->id,
                    'source_version_id' => $lockedSource->id,
                    'processing_recipe_id' => $recipe->id,
                    'requested_by' => $actor->id,
                    'automation_preferences' => ['mode' => 'reviewed_rotation', 'clockwise_degrees' => $clockwiseDegrees],
                    'state' => 'approved',
                    'attempts' => 1,
                    'started_at' => now(),
                    'completed_at' => now(),
                ]);
                \App\Domain\Media\Models\MediaFileVersion::query()
                    ->where('media_item_id', $mediaItem->id)
                    ->where('version_type', \App\Domain\Media\Enums\MediaFileVersionType::EditedFull)
                    ->update(['is_preferred' => false]);
                $version = \App\Domain\Media\Models\MediaFileVersion::query()->create([
                    'media_item_id' => $mediaItem->id,
                    'parent_version_id' => $lockedSource->id,
                    'version_type' => \App\Domain\Media\Enums\MediaFileVersionType::EditedFull,
                    'storage_disk' => 'archive_derivatives',
                    'storage_path' => $written->relativePath,
                    'mime_type' => 'image/webp',
                    'extension' => 'webp',
                    'file_size_bytes' => $written->bytes,
                    'width' => $width,
                    'height' => $height,
                    'duration_ms' => null,
                    'sha256' => $written->sha256,
                    'perceptual_hash' => null,
                    'generation_status' => \App\Domain\Media\Enums\GenerationStatus::Ready,
                    'generation_recipe' => [
                        'operation' => 'family_photo_single_rotation',
                        'source_sha256' => strtolower((string) $lockedSource->sha256),
                        'clockwise_degrees' => $clockwiseDegrees,
                        'operations' => $operations,
                        'preserves_original' => true,
                    ],
                    'is_preferred' => true,
                ]);
                $candidate = \App\Domain\Processing\Models\RestorationCandidate::query()->create([
                    'candidate_id' => (string) \Illuminate\Support\Str::uuid(),
                    'processing_job_id' => $job->id,
                    'source_version_id' => $lockedSource->id,
                    'candidate_version_id' => $version->id,
                    'quality_checks' => [
                        'source_hash_verified' => true,
                        'candidate_hash_verified' => true,
                        'visual_rotation_reviewed' => true,
                        'original_preserved' => true,
                    ],
                    'analysis' => ['clockwise_degrees' => $clockwiseDegrees, 'workflow' => 'family_photo_review'],
                    'operations_applied' => array_keys($operations),
                    'review_state' => 'approved',
                    'reviewed_by' => $actor->id,
                    'review_note' => 'Whole-photo orientation verified during the private family-photo visual review.',
                    'reviewed_at' => now(),
                ]);
                \Illuminate\Support\Facades\DB::table('cloud_import_items')->where('id', (int) $item->id)->update([
                    'restoration_candidate_id' => $candidate->id,
                    'updated_at' => now(),
                ]);
                \App\Domain\Processing\Models\ProcessingJobEvent::query()->create([
                    'processing_job_id' => $job->id,
                    'actor_id' => $actor->id,
                    'event' => 'candidate_approved',
                    'safe_context' => ['reviewed_rotation' => true, 'original_retained' => true],
                    'occurred_at' => now(),
                ]);

                return $version;
            }, 5);
        } catch (Throwable $exception) {
            $writer->removeCreated($written);

            throw $exception;
        }

        return $version;
    };

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
        $rotationDegrees = is_array($expectedSource) ? (int) ($expectedSource['rotation_degrees'] ?? 0) : 0;
        if (! $mediaItem || ! $source || ! $source->width || ! $source->height
            || ((int) $source->width * (int) $source->height) > $maximumPixels
            || ! is_array($expectedSource)
            || $expectedSource['version_id'] < 1
            || ! preg_match('/^[a-f0-9]{64}$/', $expectedSource['sha256'])
            || ! in_array($rotationDegrees, [-90, 0, 90, 180], true)
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
            if ($rotationDegrees !== 0) {
                if (! $promotion) {
                    throw new RuntimeException('A reviewed whole-photo rotation cannot mutate a shared canonical duplicate.');
                }
                $prepareRotation($session, $item, $mediaItem, $source, $actor, $rotationDegrees);
            }
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
