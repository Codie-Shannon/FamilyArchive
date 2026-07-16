<?php

namespace App\Domain\Archive\Services;

use App\Domain\Archive\Enums\ArchiveStorageDisk;
use RuntimeException;

class ArchiveStorageRegistry
{
    /** @return list<array{name: string, purpose: string, private: bool, public_url: bool, healthy: bool}> */
    public function contracts(): array
    {
        return array_map(function (ArchiveStorageDisk $disk): array {
            $archive = config("archive.disks.{$disk->value}");
            $filesystem = config("filesystems.disks.{$disk->value}");

            if (! is_array($archive) || ! is_array($filesystem)) {
                throw new RuntimeException("Archive storage disk {$disk->value} is not fully configured.");
            }

            $hasPublicContract = array_key_exists('url', $filesystem)
                || ($filesystem['visibility'] ?? 'private') === 'public'
                || ($filesystem['serve'] ?? false) === true;

            return [
                'name' => $disk->value,
                'purpose' => (string) ($archive['purpose'] ?? ''),
                'private' => ($archive['private'] ?? false) === true,
                'public_url' => ($archive['public_url'] ?? true) === true,
                'healthy' => ($archive['private'] ?? false) === true
                    && ($archive['public_url'] ?? true) === false
                    && ! $hasPublicContract,
            ];
        }, ArchiveStorageDisk::cases());
    }
}
