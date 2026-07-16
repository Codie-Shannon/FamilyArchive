<?php

use App\Domain\Archive\Enums\ArchiveStorageDisk;

it('keeps every archive disk private with no public url contract', function (): void {
    foreach (ArchiveStorageDisk::cases() as $disk) {
        $config = config("filesystems.disks.{$disk->value}");

        expect($config)->toBeArray()
            ->and($config['driver'])->toBe('local')
            ->and($config['visibility'])->toBe('private')
            ->and($config['serve'])->toBeFalse()
            ->and($config)->not->toHaveKey('url');
    }

    $links = config('filesystems.links');
    foreach (ArchiveStorageDisk::cases() as $disk) {
        expect(collect($links)->keys()->contains(fn (string $link): bool => str_contains($link, $disk->value)))->toBeFalse();
    }
});
