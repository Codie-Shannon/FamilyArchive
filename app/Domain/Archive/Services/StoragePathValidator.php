<?php

namespace App\Domain\Archive\Services;

use InvalidArgumentException;

class StoragePathValidator
{
    public function validateRelativePath(string $path): string
    {
        if ($path === '' || str_contains($path, "\0")) {
            throw new InvalidArgumentException('Storage paths cannot be empty or contain null bytes.');
        }

        if (str_contains($path, '\\') || str_starts_with($path, '/') || preg_match('/^[A-Za-z]:/', $path)) {
            throw new InvalidArgumentException('Storage paths must be relative and slash-separated.');
        }

        if (str_contains($path, ':')) {
            throw new InvalidArgumentException('Storage paths cannot contain colons.');
        }

        $segments = explode('/', $path);
        foreach ($segments as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..') {
                throw new InvalidArgumentException('Storage paths cannot contain empty or dot segments.');
            }
        }

        $extension = pathinfo(end($segments), PATHINFO_EXTENSION);
        if ($extension === '' || ! preg_match('/^[a-z0-9]+$/', $extension)) {
            throw new InvalidArgumentException('Storage extensions must be lowercase alphanumeric values.');
        }

        return $path;
    }

    public function normalizeExtension(string $extension): string
    {
        $normalized = strtolower(trim($extension));

        if ($normalized === '' || str_starts_with($normalized, '.') || ! preg_match('/^[a-z0-9]+$/', $normalized)) {
            throw new InvalidArgumentException('Extensions must be alphanumeric and must not include a leading dot.');
        }

        return $normalized;
    }

    public function sanitizeFilename(string $filename): string
    {
        if (str_contains($filename, "\0") || str_contains($filename, '/') || str_contains($filename, '\\')) {
            throw new InvalidArgumentException('Quarantine filenames must be a single safe component.');
        }

        $filename = trim($filename);
        if ($filename === '' || $filename === '.' || $filename === '..') {
            throw new InvalidArgumentException('Quarantine filenames cannot be empty or dot segments.');
        }

        $sanitized = preg_replace('/[^A-Za-z0-9._-]+/', '-', $filename);
        $sanitized = trim((string) $sanitized, '.-_');

        if ($sanitized === '' || ! str_contains($sanitized, '.')) {
            throw new InvalidArgumentException('Quarantine filenames must retain a valid extension.');
        }

        $extension = pathinfo($sanitized, PATHINFO_EXTENSION);
        $stem = substr($sanitized, 0, -(strlen($extension) + 1));
        $sanitized = $stem.'.'.$this->normalizeExtension($extension);

        return $this->validateRelativePath($sanitized);
    }
}
