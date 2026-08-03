<?php

namespace App\Domain\CloudImport\Services;

use App\Domain\CloudImport\ValueObjects\SourceExclusionBoundary;
use FilesystemIterator;
use RuntimeException;
use SplFileInfo;

final class PhotoBatchPreflight
{
    private const EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp', 'tif', 'tiff'];

    /**
     * @param  list<string>  $excludedDirectories
     * @return array{
     *     files:list<array{position:int,path:string,relative:string,relative_path_hash:string,name:string,extension:string,bytes:int,modified_at:int,content_sha256:?string,mime:?string,width:?int,height:?int,orientation:?int,captured_at:?string,valid:bool,failure_code:?string}>,
     *     summary:array<string,mixed>,
     *     inventory_sha256:string
     * }
     */
    public function scan(string $directory, bool $deep = true, array $excludedDirectories = []): array
    {
        $exclusionBoundary = SourceExclusionBoundary::forRoot($directory, $excludedDirectories);
        $root = $exclusionBoundary->root();
        $files = [];
        $ignoredExtensions = [];
        $ignoredCount = 0;
        $pendingDirectories = [$root];

        while (($currentDirectory = array_pop($pendingDirectories)) !== null) {
            $iterator = new FilesystemIterator(
                $currentDirectory,
                FilesystemIterator::SKIP_DOTS | FilesystemIterator::CURRENT_AS_FILEINFO | FilesystemIterator::KEY_AS_PATHNAME,
            );
            /** @var SplFileInfo $file */
            foreach ($iterator as $file) {
                $candidate = str_replace('\\', '/', $file->getPathname());
                if ($file->isLink()) {
                    continue;
                }
                if ($file->isDir()) {
                    if (! $exclusionBoundary->excludes($candidate)) {
                        $pendingDirectories[] = $candidate;
                    }

                    continue;
                }
                if (! $file->isFile()) {
                    continue;
                }

                $extension = strtolower($file->getExtension());
                if (! in_array($extension, self::EXTENSIONS, true)) {
                    $ignoredCount++;
                    $label = $extension !== '' ? $extension : '[none]';
                    $ignoredExtensions[$label] = ($ignoredExtensions[$label] ?? 0) + 1;

                    continue;
                }

                $resolvedPath = $file->getRealPath();
                if (! is_string($resolvedPath)) {
                    continue;
                }
                $path = str_replace('\\', '/', $resolvedPath);
                if (! str_starts_with($path, $root.'/')) {
                    throw new RuntimeException('A batch file escaped the selected directory boundary.');
                }

                $relative = substr($path, strlen($root) + 1);
                $analysis = $deep ? $this->analysePhoto($path, $extension) : $this->emptyAnalysis();
                $files[] = [
                    'path' => $path,
                    'relative' => $relative,
                    'relative_path_hash' => hash('sha256', $relative),
                    'name' => basename($relative),
                    'extension' => $extension,
                    'bytes' => $file->getSize(),
                    'modified_at' => $file->getMTime(),
                    ...$analysis,
                ];
            }
        }

        usort($files, fn (array $left, array $right): int => strcmp($left['relative'], $right['relative']));
        ksort($ignoredExtensions);

        $manifest = hash_init('sha256');
        hash_update($manifest, 'source-exclusion:'.$exclusionBoundary->fingerprint()."\n");
        $totalBytes = 0;
        $validCount = 0;
        $invalidCount = 0;
        $orientationTagged = 0;
        $capturedAtCount = 0;
        $contentHashes = [];
        $extensionCounts = [];

        foreach ($files as $position => &$file) {
            $file['position'] = $position + 1;
            $totalBytes += $file['bytes'];
            hash_update($manifest, $file['relative_path_hash'].':'.$file['bytes'].':'.$file['modified_at']."\n");
            $extensionCounts[$file['extension']] = ($extensionCounts[$file['extension']] ?? 0) + 1;
            $validCount += $file['valid'] ? 1 : 0;
            $invalidCount += $file['valid'] ? 0 : 1;
            $orientationTagged += $file['orientation'] !== null && $file['orientation'] !== 1 ? 1 : 0;
            $capturedAtCount += $file['captured_at'] !== null ? 1 : 0;
            if (is_string($file['content_sha256'])) {
                $contentHashes[$file['content_sha256']] = ($contentHashes[$file['content_sha256']] ?? 0) + 1;
            }
        }
        unset($file);
        ksort($extensionCounts);

        $duplicateGroups = array_filter($contentHashes, fn (int $count): bool => $count > 1);
        $derivativeRatio = max(0.0, (float) config('archive.batch_preflight.derivative_reserve_ratio', 0.45));
        $workingRatio = max(0.0, (float) config('archive.batch_preflight.working_reserve_ratio', 0.15));

        return [
            'files' => $files,
            'inventory_sha256' => hash_final($manifest),
            'summary' => [
                'supported_count' => count($files),
                'valid_count' => $validCount,
                'invalid_count' => $invalidCount,
                'supported_bytes' => $totalBytes,
                'ignored_count' => $ignoredCount,
                'ignored_extensions' => $ignoredExtensions,
                'extension_counts' => $extensionCounts,
                'duplicate_groups' => count($duplicateGroups),
                'duplicate_files' => array_sum($duplicateGroups) - count($duplicateGroups),
                'orientation_tagged_count' => $orientationTagged,
                'captured_at_count' => $capturedAtCount,
                'estimated_derivative_bytes' => (int) ceil($totalBytes * $derivativeRatio),
                'estimated_working_bytes' => (int) ceil($totalBytes * $workingRatio),
                'estimated_total_bytes' => (int) ceil($totalBytes * (1 + $derivativeRatio + $workingRatio)),
                'estimate_formula' => 'originals + derivative reserve + working reserve',
                'paths_persisted' => false,
                'excluded_paths_persisted' => false,
                'excluded_subtree_count' => $exclusionBoundary->count(),
                'exclusion_policy_fingerprint' => $exclusionBoundary->fingerprint(),
                'exclusion_enforcement' => 'pruned_before_discovery',
                'deep_scan' => $deep,
            ],
        ];
    }

    /** @return array{content_sha256:?string,mime:?string,width:?int,height:?int,orientation:?int,captured_at:?string,valid:bool,failure_code:?string} */
    private function analysePhoto(string $path, string $extension): array
    {
        $checksum = hash_file('sha256', $path);
        $size = @getimagesize($path);
        if (! is_string($checksum) || ! is_array($size)) {
            return ['content_sha256' => is_string($checksum) ? $checksum : null, 'mime' => null, 'width' => null, 'height' => null, 'orientation' => null, 'captured_at' => null, 'valid' => false, 'failure_code' => 'unreadable_image'];
        }

        $orientation = null;
        $capturedAt = null;
        if (in_array($extension, ['jpg', 'jpeg'], true) && function_exists('exif_read_data')) {
            $exif = @exif_read_data($path, 'IFD0,EXIF', true, false);
            if (is_array($exif)) {
                $rawOrientation = $exif['IFD0']['Orientation'] ?? null;
                $orientation = is_numeric($rawOrientation) ? (int) $rawOrientation : null;
                $rawCapturedAt = $exif['EXIF']['DateTimeOriginal'] ?? $exif['IFD0']['DateTime'] ?? null;
                $capturedAt = is_string($rawCapturedAt) && $rawCapturedAt !== '' ? $rawCapturedAt : null;
            }
        }

        return [
            'content_sha256' => $checksum,
            'mime' => $size['mime'],
            'width' => $size[0],
            'height' => $size[1],
            'orientation' => $orientation,
            'captured_at' => $capturedAt,
            'valid' => true,
            'failure_code' => null,
        ];
    }

    /** @return array{content_sha256:null,mime:null,width:null,height:null,orientation:null,captured_at:null,valid:bool,failure_code:null} */
    private function emptyAnalysis(): array
    {
        return ['content_sha256' => null, 'mime' => null, 'width' => null, 'height' => null, 'orientation' => null, 'captured_at' => null, 'valid' => true, 'failure_code' => null];
    }
}
