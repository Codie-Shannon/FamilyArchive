<?php

return static function (array $input): array {
    $sessionId = (string) ($input['session_id'] ?? '');
    $ownerEmail = (string) ($input['owner_email'] ?? '');
    $after = max(0, (int) ($input['after'] ?? 0));
    $limit = max(1, min(20, (int) ($input['limit'] ?? 5)));
    $requestedIds = array_values(array_unique(array_map('intval', $input['item_ids'] ?? [])));
    $repairDerivatives = (bool) ($input['repair_derivatives'] ?? true);

    $session = \Illuminate\Support\Facades\DB::table('cloud_import_sessions')->where('session_id', $sessionId)->firstOrFail();
    $actor = \Illuminate\Support\Facades\DB::table('users')->where('email', $ownerEmail)->firstOrFail();
    $query = \Illuminate\Support\Facades\DB::table('cloud_import_items as ci')
        ->join('archive_promotions as ap', 'ap.incoming_upload_id', '=', 'ci.incoming_upload_id')
        ->where('ci.cloud_import_session_id', $session->id)
        ->orderBy('ci.id');
    if ($requestedIds !== []) {
        $query->whereIn('ci.id', $requestedIds);
    } else {
        $query->where('ci.id', '>', $after)->limit($limit);
    }

    $items = $query->get([
        'ci.id as item_id',
        'ci.incoming_upload_id',
        'ap.media_item_id',
        'ap.original_media_file_version_id as source_version_id',
    ]);
    $results = [];

    foreach ($items as $item) {
        $itemId = (int) $item->item_id;
        try {
            $version = \App\Domain\Media\Models\MediaFileVersion::query()->findOrFail((int) $item->source_version_id);
            $incoming = \Illuminate\Support\Facades\DB::table('incoming_uploads')->where('id', (int) $item->incoming_upload_id)->firstOrFail();
            $expectedHash = strtolower((string) $version->sha256);
            $expectedBytes = (int) $version->file_size_bytes;
            if (! preg_match('/^[a-f0-9]{64}$/', $expectedHash) || $expectedBytes < 1) {
                throw new RuntimeException('The original version has invalid integrity facts.');
            }

            /** @var \App\Domain\Storage\Contracts\WasabiGateway $gateway */
            $gateway = app(\App\Domain\Storage\Contracts\WasabiGateway::class);
            /** @var \App\Domain\Storage\Services\WasabiVerifiedObjectWriter $verifiedWriter */
            $verifiedWriter = app(\App\Domain\Storage\Services\WasabiVerifiedObjectWriter::class);
            $originalPrefix = (string) config('archive_providers.providers.wasabi.prefixes.archive_originals');
            $derivativePrefix = (string) config('archive_providers.providers.wasabi.prefixes.archive_derivatives');
            $currentResult = 'verified';
            $observedBytes = null;
            $observedHash = null;
            try {
                if (! $gateway->objectExists($originalPrefix, $version->storage_path)) {
                    $currentResult = 'missing';
                } else {
                    $currentStream = $gateway->readStream($originalPrefix, $version->storage_path);
                    [$observedBytes, $observedHash] = $verifiedWriter->facts($currentStream);
                    fclose($currentStream);
                    if ($observedBytes !== $expectedBytes) {
                        $currentResult = 'size_mismatch';
                    } elseif (! hash_equals($expectedHash, $observedHash)) {
                        $currentResult = 'hash_mismatch';
                    }
                }
            } catch (Throwable $exception) {
                throw new RuntimeException('The current original could not be inspected safely.', 0, $exception);
            }

            if ($currentResult === 'verified') {
                $results[] = ['item_id' => $itemId, 'status' => 'verified', 'repaired' => false, 'derivatives_repaired' => false];
                continue;
            }
            if (
                ! (bool) $incoming->source_file_retained
                || ! is_string($incoming->incoming_path)
                || $incoming->incoming_path === ''
                || strtolower((string) $incoming->sha256) !== $expectedHash
                || (int) $incoming->file_size_bytes !== $expectedBytes
            ) {
                throw new RuntimeException('The retained recovery record does not match the original integrity identity.');
            }

            $quarantine = \Illuminate\Support\Facades\Storage::disk('archive_quarantine');
            $retainedBytes = $quarantine->get($incoming->incoming_path);
            if (strlen($retainedBytes) !== $expectedBytes || ! hash_equals($expectedHash, hash('sha256', $retainedBytes))) {
                throw new RuntimeException('The retained recovery object failed exact integrity verification.');
            }

            $extension = strtolower((string) ($version->extension ?: $incoming->extension ?: 'bin'));
            $extension = preg_match('/^[a-z0-9]{1,16}$/', $extension) ? $extension : 'bin';
            $recoveryPath = sprintf(
                'recovery/photos/%s/%d-%d-%s.%s',
                substr($expectedHash, 0, 2),
                (int) $item->media_item_id,
                (int) $version->id,
                $expectedHash,
                $extension,
            );
            if ($gateway->objectExists($originalPrefix, $recoveryPath)) {
                $recoveredStream = $gateway->readStream($originalPrefix, $recoveryPath);
                [$recoveredSize, $recoveredHash] = $verifiedWriter->facts($recoveredStream);
                fclose($recoveredStream);
                if ($recoveredSize !== $expectedBytes || ! hash_equals($expectedHash, $recoveredHash)) {
                    throw new RuntimeException('The deterministic recovery target already exists with different bytes.');
                }
            } else {
                /** @var \App\Domain\Archive\Contracts\NoOverwriteOriginalWriter $originalWriter */
                $originalWriter = app(\App\Domain\Archive\Contracts\NoOverwriteOriginalWriter::class);
                $writtenOriginal = $originalWriter->copyFromQuarantine(
                    $incoming->incoming_path,
                    $recoveryPath,
                    $expectedBytes,
                    $expectedHash,
                );
                if ($writtenOriginal->storedBytes !== $expectedBytes || ! hash_equals($expectedHash, $writtenOriginal->storedSha256)) {
                    throw new RuntimeException('The recovery original failed readback verification.');
                }
            }

            $derivatives = [];
            $mediaItem = \Illuminate\Support\Facades\DB::table('media_items')->where('id', (int) $item->media_item_id)->firstOrFail();
            if ($repairDerivatives && (string) $mediaItem->review_status === 'approved') {
                /** @var \App\Domain\Derivatives\Services\GdPhotoDerivativeEncoder $encoder */
                $encoder = app(\App\Domain\Derivatives\Services\GdPhotoDerivativeEncoder::class);
                /** @var \App\Domain\Derivatives\Services\PhotoDerivativeRecipe $recipe */
                $recipe = app(\App\Domain\Derivatives\Services\PhotoDerivativeRecipe::class);
                /** @var \App\Domain\Derivatives\Contracts\NoOverwriteDerivativeWriter $writer */
                $writer = app(\App\Domain\Derivatives\Contracts\NoOverwriteDerivativeWriter::class);
                foreach ($recipe->types() as $type) {
                    $target = $recipe->target($type);
                    $encoded = $encoder->encode(
                        $retainedBytes,
                        (string) $version->mime_type,
                        $target['max_long_side'],
                        $target['quality'],
                    );
                    $derivativePath = sprintf(
                        'recovery/photos/%d/%s/%s.webp',
                        (int) $item->media_item_id,
                        $expectedHash,
                        $type->value,
                    );
                    $encodedHash = hash('sha256', $encoded->bytes);
                    if ($gateway->objectExists($derivativePrefix, $derivativePath)) {
                        $storedStream = $gateway->readStream($derivativePrefix, $derivativePath);
                        [$storedSize, $storedHash] = $verifiedWriter->facts($storedStream);
                        fclose($storedStream);
                        if ($storedSize !== strlen($encoded->bytes) || ! hash_equals($encodedHash, $storedHash)) {
                            throw new RuntimeException("The {$type->value} recovery target already exists with different bytes.");
                        }
                    } else {
                        $written = $writer->write($derivativePath, $encoded->bytes);
                        if ($written->bytes !== strlen($encoded->bytes) || ! hash_equals($encodedHash, $written->sha256)) {
                            throw new RuntimeException("The {$type->value} recovery derivative failed writer verification.");
                        }
                    }
                    $derivatives[$type->value] = [
                        'path' => $derivativePath,
                        'sha256' => $encodedHash,
                        'bytes' => strlen($encoded->bytes),
                        'width' => $encoded->width,
                        'height' => $encoded->height,
                        'recipe' => $recipe->metadata(
                            $type,
                            $expectedHash,
                            $encoded->quality,
                            $encoded->maxLongSide,
                            $encoded->encoder,
                            $encoded->sourceOrientation,
                            $encoded->orientationApplied,
                        ),
                    ];
                }
            }

            $caseId = \Illuminate\Support\Facades\DB::transaction(static function () use (
                $actor,
                $currentResult,
                $derivatives,
                $expectedBytes,
                $expectedHash,
                $item,
                $observedBytes,
                $observedHash,
                $recoveryPath,
                $version,
            ): int {
                $locked = \App\Domain\Media\Models\MediaFileVersion::query()->lockForUpdate()->findOrFail($version->id);
                if (! hash_equals(strtolower((string) $locked->sha256), $expectedHash) || (int) $locked->file_size_bytes !== $expectedBytes) {
                    throw new RuntimeException('The original integrity identity changed during recovery.');
                }
                $checkId = \Illuminate\Support\Facades\DB::table('integrity_checks')->insertGetId([
                    'check_id' => (string) \Illuminate\Support\Str::uuid(),
                    'media_file_version_id' => $locked->id,
                    'result' => $currentResult,
                    'observed' => json_encode([
                        'expected_bytes' => $expectedBytes,
                        'expected_sha256' => $expectedHash,
                        'observed_bytes' => $observedBytes,
                        'observed_sha256' => $observedHash,
                        'recovery_source_verified' => true,
                    ], JSON_THROW_ON_ERROR),
                    'checked_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $caseId = \Illuminate\Support\Facades\DB::table('repair_cases')->insertGetId([
                    'case_id' => (string) \Illuminate\Support\Str::uuid(),
                    'integrity_check_id' => $checkId,
                    'state' => 'repaired',
                    'recovery_source' => 'retained_quarantine',
                    'new_object_path' => $recoveryPath,
                    'approved_by' => $actor->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $locked->forceFill(['storage_disk' => 'archive_originals', 'storage_path' => $recoveryPath])->save();

                foreach ($derivatives as $typeValue => $data) {
                    $type = \App\Domain\Media\Enums\MediaFileVersionType::from($typeValue);
                    \App\Domain\Media\Models\MediaFileVersion::query()
                        ->where('media_item_id', (int) $item->media_item_id)
                        ->where('version_type', $type)
                        ->where('is_preferred', true)
                        ->update(['is_preferred' => false]);
                    \App\Domain\Media\Models\MediaFileVersion::query()->updateOrCreate(
                        ['storage_path' => $data['path']],
                        [
                            'media_item_id' => (int) $item->media_item_id,
                            'parent_version_id' => $locked->id,
                            'version_type' => $type,
                            'storage_disk' => 'archive_derivatives',
                            'mime_type' => 'image/webp',
                            'extension' => 'webp',
                            'file_size_bytes' => $data['bytes'],
                            'width' => $data['width'],
                            'height' => $data['height'],
                            'duration_ms' => null,
                            'sha256' => $data['sha256'],
                            'perceptual_hash' => null,
                            'generation_status' => \App\Domain\Media\Enums\GenerationStatus::Ready,
                            'generation_recipe' => $data['recipe'],
                            'is_preferred' => true,
                        ],
                    );
                }

                return $caseId;
            }, 5);

            $fresh = \App\Domain\Media\Models\MediaFileVersion::query()->findOrFail($version->id);
            $verifiedStream = $gateway->readStream($originalPrefix, $fresh->storage_path);
            [$verifiedSize, $verifiedHash] = $verifiedWriter->facts($verifiedStream);
            fclose($verifiedStream);
            if ($verifiedSize !== $expectedBytes || ! hash_equals($expectedHash, $verifiedHash)) {
                throw new RuntimeException('The repaired database cutover failed final object verification.');
            }
            foreach ($derivatives as $data) {
                $storedStream = $gateway->readStream($derivativePrefix, $data['path']);
                [$storedSize, $storedHash] = $verifiedWriter->facts($storedStream);
                fclose($storedStream);
                if ($storedSize !== $data['bytes'] || ! hash_equals($data['sha256'], $storedHash)) {
                    throw new RuntimeException('A repaired derivative failed final object verification.');
                }
            }
            \Illuminate\Support\Facades\DB::table('repair_cases')->where('id', $caseId)->update(['state' => 'closed', 'updated_at' => now()]);
            $results[] = [
                'item_id' => $itemId,
                'status' => 'repaired',
                'repaired' => true,
                'derivatives_repaired' => $derivatives !== [],
            ];
        } catch (Throwable $exception) {
            $results[] = [
                'item_id' => $itemId,
                'status' => 'failed',
                'repaired' => false,
                'derivatives_repaired' => false,
                'error' => mb_substr($exception->getMessage(), 0, 240),
            ];
        }
    }

    return [
        'items' => $results,
        'last_item_id' => $items->isEmpty() ? $after : (int) $items->max('item_id'),
        'returned_count' => $items->count(),
    ];
};
