<?php

namespace App\Console\Commands;

use App\Domain\CloudImport\Services\PhotoBatchPreflight;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use RuntimeException;

final class PreflightPhotoBatchCommand extends Command
{
    protected $signature = 'archive:batch-preflight {directory} {--exclude=* : Source subtree to prune before discovery; repeat for multiple directories} {--json=} {--csv=}';

    protected $description = 'Read and report a local photo inventory without retaining or modifying source files';

    public function handle(PhotoBatchPreflight $preflight): int
    {
        $result = $preflight->scan((string) $this->argument('directory'), true, $this->excludedDirectories());
        $summary = $result['summary'];
        $this->info('Source-safe migration preflight complete. No source bytes were changed or retained.');
        $this->table(['Measure', 'Result'], [
            ['Supported photos', number_format((int) $summary['supported_count'])],
            ['Readable photos', number_format((int) $summary['valid_count'])],
            ['Unreadable photos', number_format((int) $summary['invalid_count'])],
            ['Ignored non-photo files', number_format((int) $summary['ignored_count'])],
            ['Excluded source subtrees', number_format((int) $summary['excluded_subtree_count'])],
            ['Duplicate candidates', number_format((int) $summary['duplicate_files'])],
            ['Source bytes', $this->formatBytes((int) $summary['supported_bytes'])],
            ['Planned storage reserve', $this->formatBytes((int) $summary['estimated_total_bytes'])],
        ]);

        $report = [
            'generated_at' => now()->toIso8601String(),
            'inventory_sha256' => $result['inventory_sha256'],
            'summary' => $summary,
            'files' => array_map(fn (array $file): array => [
                'position' => $file['position'],
                'relative_path' => $file['relative'],
                'name' => $file['name'],
                'extension' => $file['extension'],
                'bytes' => $file['bytes'],
                'content_sha256' => $file['content_sha256'],
                'mime' => $file['mime'],
                'width' => $file['width'],
                'height' => $file['height'],
                'orientation' => $file['orientation'],
                'captured_at' => $file['captured_at'],
                'valid' => $file['valid'],
                'failure_code' => $file['failure_code'],
            ], $result['files']),
        ];

        $json = $this->option('json');
        if (is_string($json) && $json !== '') {
            $this->writeReport($json, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR).PHP_EOL);
            $this->line('JSON report: '.$json);
        }

        $csv = $this->option('csv');
        if (is_string($csv) && $csv !== '') {
            $rows = ['position,relative_path,name,extension,bytes,content_sha256,mime,width,height,orientation,captured_at,valid,failure_code'];
            foreach ($report['files'] as $file) {
                $rows[] = implode(',', array_map(fn (mixed $value): string => $this->csv($value), array_values($file)));
            }
            $this->writeReport($csv, implode(PHP_EOL, $rows).PHP_EOL);
            $this->line('CSV report: '.$csv);
        }

        return (int) $summary['invalid_count'] > 0 ? self::FAILURE : self::SUCCESS;
    }

    /** @return list<string> */
    private function excludedDirectories(): array
    {
        return array_values(array_map('strval', (array) $this->option('exclude')));
    }

    private function writeReport(string $path, string $contents): void
    {
        $parent = dirname($path);
        if (! File::isDirectory($parent) || ! File::isWritable($parent)) {
            throw new RuntimeException('The report directory must already exist and be writable.');
        }
        File::put($path, $contents, true);
    }

    private function csv(mixed $value): string
    {
        $string = is_bool($value) ? ($value ? 'true' : 'false') : (string) ($value ?? '');

        return '"'.str_replace('"', '""', $string).'"';
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes.' B';
        }
        $units = ['KiB', 'MiB', 'GiB', 'TiB'];
        $value = $bytes / 1024;
        foreach ($units as $unit) {
            if ($value < 1024 || $unit === 'TiB') {
                return number_format($value, 2).' '.$unit;
            }
            $value /= 1024;
        }

        return number_format($value, 2).' TiB';
    }
}
