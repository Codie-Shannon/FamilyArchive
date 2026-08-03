<?php

namespace App\Domain\CloudImport\ValueObjects;

use RuntimeException;

final readonly class SourceExclusionBoundary
{
    /** @param list<string> $directories */
    private function __construct(
        private string $root,
        private array $directories,
        private string $fingerprint,
    ) {}

    /** @param list<string> $requestedDirectories */
    public static function forRoot(string $root, array $requestedDirectories): self
    {
        $resolvedRoot = realpath($root);
        if (! is_string($resolvedRoot) || ! is_dir($resolvedRoot)) {
            throw new RuntimeException('The batch source directory does not exist.');
        }

        $resolvedRoot = self::normalize($resolvedRoot);
        $directories = [];
        foreach ($requestedDirectories as $requestedDirectory) {
            $requestedDirectory = trim($requestedDirectory);
            if ($requestedDirectory === '') {
                throw new RuntimeException('An excluded source directory cannot be empty.');
            }

            $candidate = self::isAbsolute($requestedDirectory)
                ? $requestedDirectory
                : $resolvedRoot.'/'.str_replace('\\', '/', $requestedDirectory);
            $resolved = realpath($candidate);
            if (! is_string($resolved) || ! is_dir($resolved)) {
                throw new RuntimeException('An excluded source directory does not exist; preflight stopped before discovery.');
            }

            $resolved = self::normalize($resolved);
            if (! self::isStrictDescendant($resolvedRoot, $resolved)) {
                throw new RuntimeException('Every excluded source directory must be a strict descendant of the batch source.');
            }

            $directories[] = $resolved;
        }

        usort($directories, fn (string $left, string $right): int => strcmp(self::comparable($left), self::comparable($right)));
        $directories = array_values(array_unique($directories));
        $effective = [];
        foreach ($directories as $directory) {
            $covered = false;
            foreach ($effective as $parent) {
                if (self::isSameOrDescendant($parent, $directory)) {
                    $covered = true;

                    break;
                }
            }
            if ($covered) {
                continue;
            }

            $effective[] = $directory;
        }

        $relativePolicy = array_map(
            fn (string $directory): string => substr($directory, strlen($resolvedRoot) + 1),
            $effective,
        );
        $key = (string) config('app.key');
        if (str_starts_with($key, 'base64:')) {
            $decoded = base64_decode(substr($key, 7), true);
            $key = is_string($decoded) ? $decoded : $key;
        }

        return new self(
            $resolvedRoot,
            $effective,
            hash_hmac('sha256', implode("\n", $relativePolicy), $key),
        );
    }

    public function root(): string
    {
        return $this->root;
    }

    public function count(): int
    {
        return count($this->directories);
    }

    public function fingerprint(): string
    {
        return $this->fingerprint;
    }

    public function excludes(string $path): bool
    {
        $path = self::normalize($path);

        foreach ($this->directories as $directory) {
            if (self::isSameOrDescendant($directory, $path)) {
                return true;
            }
        }

        return false;
    }

    private static function isStrictDescendant(string $parent, string $candidate): bool
    {
        return self::comparable($candidate) !== self::comparable($parent)
            && str_starts_with(self::comparable($candidate), self::comparable($parent).'/');
    }

    private static function isSameOrDescendant(string $parent, string $candidate): bool
    {
        return self::comparable($candidate) === self::comparable($parent)
            || str_starts_with(self::comparable($candidate), self::comparable($parent).'/');
    }

    private static function normalize(string $path): string
    {
        return rtrim(str_replace('\\', '/', $path), '/');
    }

    private static function comparable(string $path): string
    {
        $path = self::normalize($path);

        return DIRECTORY_SEPARATOR === '\\' ? strtolower($path) : $path;
    }

    private static function isAbsolute(string $path): bool
    {
        return str_starts_with($path, '/')
            || str_starts_with($path, '\\\\')
            || preg_match('/^[A-Za-z]:[\\\\\/]/', $path) === 1;
    }
}
