<?php

return static function (array $input): array {
    $sessionId = (string) ($input['session_id'] ?? '');
    $ownerEmail = (string) ($input['owner_email'] ?? '');
    $itemIds = array_values(array_unique(array_map('intval', $input['item_ids'] ?? [])));
    $session = \Illuminate\Support\Facades\DB::table('cloud_import_sessions')
        ->where('session_id', $sessionId)
        ->firstOrFail();
    $actor = \App\Models\User::query()->where('email', $ownerEmail)->firstOrFail();
    $done = [];
    $failed = [];

    foreach ($itemIds as $itemId) {
        try {
            $done[] = \Illuminate\Support\Facades\DB::transaction(static function () use ($session, $actor, $itemId): int {
                $item = \Illuminate\Support\Facades\DB::table('cloud_import_items')
                    ->where('cloud_import_session_id', (int) $session->id)
                    ->where('id', $itemId)
                    ->lockForUpdate()
                    ->firstOrFail();
                $proposal = \App\Domain\Processing\Models\PhotoSplitProposal::query()
                    ->where('cloud_import_item_id', $itemId)
                    ->first();
                if ($proposal?->state === 'published') {
                    throw new RuntimeException('A published split cannot be excluded.');
                }
                if ($proposal) {
                    $proposal->forceFill([
                        'state' => 'dismissed',
                        'reviewed_by' => $actor->id,
                        'reviewed_at' => now(),
                    ])->save();
                }

                $promotion = \App\Domain\Archive\Models\ArchivePromotion::query()
                    ->where('incoming_upload_id', $item->incoming_upload_id)
                    ->first();
                if ($promotion) {
                    $source = \App\Domain\Media\Models\MediaFileVersion::query()
                        ->find($promotion->original_media_file_version_id);
                } else {
                    $upload = \App\Domain\Intake\Models\IncomingUpload::query()
                        ->find($item->incoming_upload_id);
                    $source = $upload === null ? null : \App\Domain\Media\Models\MediaFileVersion::query()
                        ->where('version_type', 'original')
                        ->where('sha256', $upload->sha256)
                        ->orderBy('id')
                        ->first();
                }
                $mediaItem = $source === null ? null : \App\Domain\Media\Models\MediaItem::query()->find($source->media_item_id);
                if ($mediaItem) {
                    $mediaItem->forceFill([
                        'review_status' => \App\Domain\Media\Enums\MediaReviewStatus::Hidden,
                        'visibility' => \App\Domain\Media\Enums\MediaVisibility::PrivateArchive,
                        'approved_by' => null,
                        'approved_at' => null,
                    ])->save();
                }
                \Illuminate\Support\Facades\DB::table('cloud_import_items')->where('id', $itemId)->update([
                    'review_decision' => 'hold',
                    'attention_code' => 'split_review_excluded',
                    'reviewed_by' => $actor->id,
                    'reviewed_at' => now(),
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
