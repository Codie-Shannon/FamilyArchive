<?php

use App\Domain\Intake\Models\IncomingUpload;
use App\Domain\Media\Models\MediaFileVersion;
use App\Domain\Media\Models\MediaItem;
use Database\Seeders\Group02DemoSeeder;

it('refuses to seed outside the local environment', function (): void {
    expect(fn () => $this->seed(Group02DemoSeeder::class))
        ->toThrow(\RuntimeException::class, 'only in the local environment');
});

it('seeds a stable fictional relationship set idempotently in local', function (): void {
    $originalEnvironment = app()->environment();
    app()->instance('env', 'local');

    try {
        $this->seed(Group02DemoSeeder::class);
        $this->seed(Group02DemoSeeder::class);
    } finally {
        app()->instance('env', $originalEnvironment);
    }

    expect(MediaItem::query()->where('archive_id', 'FA-DEMO-00000001')->count())->toBe(1)
        ->and(IncomingUpload::query()->where('upload_id', 'UP-DEMO-00000001')->count())->toBe(1)
        ->and(MediaFileVersion::query()->count())->toBe(3);

    $item = MediaItem::query()
        ->with(['incomingUploads', 'fileVersions.parentVersion'])
        ->where('archive_id', 'FA-DEMO-00000001')
        ->firstOrFail();

    expect($item->incomingUploads)->toHaveCount(1)
        ->and($item->fileVersions)->toHaveCount(3)
        ->and($item->fileVersions->whereNotNull('parent_version_id'))->toHaveCount(2);
});

it('refuses to mix demo records into a database containing non-demo media', function (): void {
    MediaItem::factory()->create([
        'archive_id' => 'FA-REAL-RECORD-0001',
    ]);

    $originalEnvironment = app()->environment();
    app()->instance('env', 'local');

    try {
        expect(fn () => $this->seed(Group02DemoSeeder::class))
            ->toThrow(\RuntimeException::class, 'non-demo media records exist');
    } finally {
        app()->instance('env', $originalEnvironment);
    }
});
