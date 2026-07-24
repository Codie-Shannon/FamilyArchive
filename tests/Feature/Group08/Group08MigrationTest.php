<?php

use App\Domain\Archive\Models\ArchivePromotion;
use Illuminate\Support\Facades\Schema;

it('adds the photo media type and restrictive immutable promotion audit schema', function () {
    expect(Schema::hasColumn('incoming_uploads', 'media_type'))->toBeTrue()
        ->and(Schema::hasTable('archive_promotions'))->toBeTrue()
        ->and(Schema::hasColumns('archive_promotions', [
            'incoming_upload_id',
            'media_item_id',
            'original_media_file_version_id',
            'actor_id',
            'source_disk',
            'source_path',
            'target_disk',
            'target_path',
            'source_bytes',
            'target_bytes',
            'source_sha256',
            'target_sha256',
            'promoted_at',
        ]))->toBeTrue();

    expect((new ArchivePromotion)->getTable())->toBe('archive_promotions');
});
