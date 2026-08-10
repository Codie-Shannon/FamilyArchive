<?php

return static function (array $input): array {
    $sessionId = (string) ($input['session_id'] ?? '');
    $ownerEmail = (string) ($input['owner_email'] ?? '');
    $itemIds = array_values(array_unique(array_map('intval', $input['item_ids'] ?? [])));
    $expectedSources = [];
    foreach (($input['expected_sources'] ?? []) as $expectedSource) {
        $expectedSources[(int) ($expectedSource['item_id'] ?? 0)] = [
            'version_id' => (int) ($expectedSource['source_version_id'] ?? 0),
            'sha256' => strtolower((string) ($expectedSource['source_sha256'] ?? '')),
        ];
    }
    $session = \Illuminate\Support\Facades\DB::table('cloud_import_sessions')
        ->where('session_id', $sessionId)
        ->firstOrFail();
    $actor = \App\Models\User::query()->where('email', $ownerEmail)->firstOrFail();
    $done = [];
    $failed = [];

    foreach ($itemIds as $itemId) {
        try {
            $done[] = \Illuminate\Support\Facades\DB::transaction(static function () use ($session, $actor, $itemId, $expectedSources): int {
                $item = \Illuminate\Support\Facades\DB::table('cloud_import_items')
                    ->where('cloud_import_session_id', (int) $session->id)
                    ->where('id', $itemId)
                    ->lockForUpdate()
                    ->firstOrFail();
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
                $expectedSource = $expectedSources[$itemId] ?? null;
                if (! $source
                    || ! is_array($expectedSource)
                    || $expectedSource['version_id'] < 1
                    || ! preg_match('/^[a-f0-9]{64}$/', $expectedSource['sha256'])
                    || (int) $source->id !== $expectedSource['version_id']
                    || ! hash_equals($expectedSource['sha256'], strtolower((string) $source->sha256))) {
                    throw new RuntimeException('The reviewed single decision no longer matches its immutable census source.');
                }

                $proposal = \App\Domain\Processing\Models\PhotoSplitProposal::query()
                    ->where('cloud_import_item_id', $itemId)
                    ->lockForUpdate()
                    ->first();
                if ($proposal?->state === 'published') {
                    throw new RuntimeException('A published split cannot be changed to single.');
                }
                if ($proposal) {
                    $proposal->forceFill([
                        'state' => 'dismissed',
                        'reviewed_by' => $actor->id,
                        'reviewed_at' => now(),
                    ])->save();
                }
                \Illuminate\Support\Facades\DB::table('cloud_import_items')->where('id', $itemId)->update([
                    'attention_code' => in_array($item->attention_code, ['multiple_photos_detected', 'multi_photo_ready'], true)
                        ? null
                        : $item->attention_code,
                    'updated_at' => now(),
                ]);

                return $itemId;
            });
        } catch (Throwable $exception) {
            $failed[] = [
                'id' => $itemId,
                'error' => mb_substr($exception->getMessage(), 0, 180),
            ];
        }
    }

    return ['done' => $done, 'failed' => $failed];
};
