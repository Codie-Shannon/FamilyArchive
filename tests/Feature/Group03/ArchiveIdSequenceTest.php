<?php

use App\Domain\Archive\Models\ArchiveIdSequence;
use App\Domain\Archive\Services\ArchiveIdGenerator;
use App\Domain\Media\Enums\MediaType;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

it('creates the forward-only sequence schema with one unique media type row', function (): void {
    expect(Schema::hasColumns('archive_id_sequences', ['media_type', 'last_value']))->toBeTrue();

    $indexes = DB::select("PRAGMA index_list('archive_id_sequences')");
    $uniqueMediaTypeIndex = collect($indexes)->first(function (object $index): bool {
        if ((int) ($index->unique ?? 0) !== 1) {
            return false;
        }

        $columns = collect(DB::select("PRAGMA index_info('".$index->name."')"))
            ->pluck('name')
            ->all();

        return $columns === ['media_type'];
    });

    expect($uniqueMediaTypeIndex)->not->toBeNull();
});

it('allocates unique monotonically increasing ids from one transaction-backed sequence', function (): void {
    $generator = app(ArchiveIdGenerator::class);

    expect($generator->allocate(MediaType::Photo))->toBe('PH_000001')
        ->and($generator->allocate(MediaType::Photo))->toBe('PH_000002')
        ->and($generator->allocate(MediaType::Video))->toBe('VD_000001');

    expect(ArchiveIdSequence::query()->where('media_type', 'photo')->value('last_value'))->toBe(2)
        ->and(ArchiveIdSequence::query()->where('media_type', 'video')->value('last_value'))->toBe(1);
});

it('does not reuse an allocated value after a later allocation', function (): void {
    $generator = app(ArchiveIdGenerator::class);
    $first = $generator->allocate(MediaType::Document);
    $second = $generator->allocate(MediaType::Document);

    expect($first)->toBe('DC_000001')->and($second)->toBe('DC_000002')->and($second)->not->toBe($first);
});
