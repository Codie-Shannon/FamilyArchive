<?php

use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

it('creates the three core tables with the required columns', function (): void {
    expect(Schema::hasColumns('media_items', [
        'id', 'archive_id', 'media_type', 'title', 'description', 'story',
        'canonical_date', 'estimated_decade', 'date_confidence', 'visibility',
        'review_status', 'sensitivity_status', 'created_by', 'approved_by',
        'approved_at', 'created_at', 'updated_at',
    ]))->toBeTrue();

    expect(Schema::hasColumns('incoming_uploads', [
        'id', 'upload_id', 'uploader_id', 'reviewed_by', 'media_item_id',
        'original_filename', 'incoming_path', 'mime_type', 'extension',
        'file_size_bytes', 'width', 'height', 'duration_ms', 'sha256',
        'perceptual_hash', 'processing_status', 'review_status',
        'duplicate_status', 'source_file_retained', 'source_file_removed_at',
        'submitted_at', 'reviewed_at', 'created_at', 'updated_at',
    ]))->toBeTrue();

    expect(Schema::hasColumns('media_file_versions', [
        'id', 'media_item_id', 'parent_version_id', 'version_type',
        'storage_disk', 'storage_path', 'mime_type', 'extension',
        'file_size_bytes', 'width', 'height', 'duration_ms', 'sha256',
        'perceptual_hash', 'generation_status', 'generation_recipe',
        'is_preferred', 'created_at', 'updated_at',
    ]))->toBeTrue();
});

it('applies safe defaults to new records', function (): void {
    $user = User::factory()->create();

    $mediaItemId = DB::table('media_items')->insertGetId([
        'archive_id' => 'FA-DEMO-DEFAULTS-01',
        'media_type' => 'photo',
        'created_by' => $user->id,
    ]);

    $mediaItem = DB::table('media_items')->find($mediaItemId);

    expect($mediaItem)->not->toBeNull()
        ->and($mediaItem->date_confidence)->toBe('unknown')
        ->and($mediaItem->visibility)->toBe('private_archive')
        ->and($mediaItem->review_status)->toBe('pending_review')
        ->and($mediaItem->sensitivity_status)->toBe('not_flagged');

    $incomingId = DB::table('incoming_uploads')->insertGetId([
        'upload_id' => 'UP-DEMO-DEFAULTS-01',
        'uploader_id' => $user->id,
        'original_filename' => 'fictional-defaults.jpg',
        'mime_type' => 'image/jpeg',
        'file_size_bytes' => 10,
        'sha256' => str_repeat('a', 64),
    ]);

    $incoming = DB::table('incoming_uploads')->find($incomingId);

    expect($incoming)->not->toBeNull()
        ->and($incoming->processing_status)->toBe('pending')
        ->and($incoming->review_status)->toBe('pending_review')
        ->and($incoming->duplicate_status)->toBe('not_checked')
        ->and((int) $incoming->source_file_retained)->toBe(1);

    $versionId = DB::table('media_file_versions')->insertGetId([
        'media_item_id' => $mediaItemId,
        'version_type' => 'original',
        'storage_disk' => 'local',
        'storage_path' => 'demo/defaults/original.jpg',
        'mime_type' => 'image/jpeg',
        'file_size_bytes' => 10,
        'sha256' => str_repeat('b', 64),
    ]);

    $version = DB::table('media_file_versions')->find($versionId);

    expect($version)->not->toBeNull()
        ->and($version->generation_status)->toBe('pending')
        ->and((int) $version->is_preferred)->toBe(0);
});

it('enforces stable unique identities and storage paths', function (): void {
    $user = User::factory()->create();

    $mediaItemId = DB::table('media_items')->insertGetId([
        'archive_id' => 'FA-DEMO-UNIQUE-01',
        'media_type' => 'photo',
        'created_by' => $user->id,
    ]);

    expect(fn () => DB::table('media_items')->insert([
        'archive_id' => 'FA-DEMO-UNIQUE-01',
        'media_type' => 'video',
        'created_by' => $user->id,
    ]))->toThrow(QueryException::class);

    DB::table('incoming_uploads')->insert([
        'upload_id' => 'UP-DEMO-UNIQUE-01',
        'uploader_id' => $user->id,
        'original_filename' => 'fictional-unique.jpg',
        'mime_type' => 'image/jpeg',
        'file_size_bytes' => 10,
        'sha256' => str_repeat('c', 64),
    ]);

    expect(fn () => DB::table('incoming_uploads')->insert([
        'upload_id' => 'UP-DEMO-UNIQUE-01',
        'uploader_id' => $user->id,
        'original_filename' => 'fictional-duplicate.jpg',
        'mime_type' => 'image/jpeg',
        'file_size_bytes' => 11,
        'sha256' => str_repeat('d', 64),
    ]))->toThrow(QueryException::class);

    DB::table('media_file_versions')->insert([
        'media_item_id' => $mediaItemId,
        'version_type' => 'original',
        'storage_disk' => 'local',
        'storage_path' => 'demo/archive/unique/original.jpg',
        'mime_type' => 'image/jpeg',
        'file_size_bytes' => 10,
        'sha256' => str_repeat('e', 64),
    ]);

    expect(fn () => DB::table('media_file_versions')->insert([
        'media_item_id' => $mediaItemId,
        'version_type' => 'thumbnail',
        'storage_disk' => 'local',
        'storage_path' => 'demo/archive/unique/original.jpg',
        'mime_type' => 'image/webp',
        'file_size_bytes' => 5,
        'sha256' => str_repeat('f', 64),
    ]))->toThrow(QueryException::class);
});

it('creates the required indexes and restrictive foreign keys', function (): void {
    $mediaIndexes = collect(Schema::getIndexes('media_items'))->pluck('name');
    $incomingIndexes = collect(Schema::getIndexes('incoming_uploads'))->pluck('name');
    $versionIndexes = collect(Schema::getIndexes('media_file_versions'))->pluck('name');

    expect($mediaIndexes)->toContain('media_items_archive_id_unique')
        ->and($mediaIndexes)->toContain('media_items_media_type_index')
        ->and($mediaIndexes)->toContain('media_items_visibility_index')
        ->and($mediaIndexes)->toContain('media_items_review_status_index')
        ->and($mediaIndexes)->toContain('media_items_sensitivity_status_index')
        ->and($incomingIndexes)->toContain('incoming_uploads_upload_id_unique')
        ->and($incomingIndexes)->toContain('incoming_uploads_sha256_index')
        ->and($incomingIndexes)->toContain('incoming_uploads_perceptual_hash_index')
        ->and($incomingIndexes)->toContain('incoming_uploads_processing_status_index')
        ->and($incomingIndexes)->toContain('incoming_uploads_review_status_index')
        ->and($incomingIndexes)->toContain('incoming_uploads_duplicate_status_index')
        ->and($versionIndexes)->toContain('media_file_versions_storage_path_unique')
        ->and($versionIndexes)->toContain('media_file_versions_version_type_index')
        ->and($versionIndexes)->toContain('media_file_versions_sha256_index')
        ->and($versionIndexes)->toContain('media_file_versions_perceptual_hash_index')
        ->and($versionIndexes)->toContain('media_file_versions_generation_status_index')
        ->and($versionIndexes)->toContain('media_file_versions_is_preferred_index');

    $mediaForeignKeys = collect(Schema::getForeignKeys('media_items'));
    $incomingForeignKeys = collect(Schema::getForeignKeys('incoming_uploads'));
    $versionForeignKeys = collect(Schema::getForeignKeys('media_file_versions'));

    expect($mediaForeignKeys)->toHaveCount(2)
        ->and($incomingForeignKeys)->toHaveCount(3)
        ->and($versionForeignKeys)->toHaveCount(2);

    foreach ($mediaForeignKeys->concat($incomingForeignKeys)->concat($versionForeignKeys) as $foreignKey) {
        expect(strtolower((string) $foreignKey['on_delete']))->toBeIn(['restrict', 'no action']);
    }
});
