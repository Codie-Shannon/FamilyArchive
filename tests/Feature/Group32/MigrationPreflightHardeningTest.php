<?php

use App\Domain\CloudImport\Services\HighVolumePhotoBatch;
use App\Domain\CloudImport\Services\PhotoBatchPreflight;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

function sg32Directory(): string
{
    $directory = storage_path('framework/testing/sg32-'.str()->random(10));
    File::ensureDirectoryExists($directory);
    $photo = UploadedFile::fake()->image('source.jpg', 120, 80);
    File::copy($photo->getRealPath(), $directory.'/photo-a.jpg');
    File::copy($photo->getRealPath(), $directory.'/photo-b.jpg');
    File::put($directory.'/corrupt.jpg', 'not an image');
    File::put($directory.'/notes.txt', 'ignored migration note');

    return $directory;
}

it('preflights content without changing source files and reports exceptions safely', function (): void {
    $directory = sg32Directory();
    $before = collect(File::allFiles($directory))->mapWithKeys(fn (SplFileInfo $file): array => [$file->getFilename() => hash_file('sha256', $file->getPathname())])->all();

    try {
        $result = app(PhotoBatchPreflight::class)->scan($directory);
        $after = collect(File::allFiles($directory))->mapWithKeys(fn (SplFileInfo $file): array => [$file->getFilename() => hash_file('sha256', $file->getPathname())])->all();

        expect($result['summary'])->toMatchArray([
            'supported_count' => 3,
            'valid_count' => 2,
            'invalid_count' => 1,
            'ignored_count' => 1,
            'duplicate_groups' => 1,
            'duplicate_files' => 1,
            'paths_persisted' => false,
        ])->and($before)->toBe($after)
            ->and($result['summary']['estimated_total_bytes'])->toBeGreaterThan($result['summary']['supported_bytes']);
    } finally {
        File::deleteDirectory($directory);
    }
});

it('writes an operator report with relative paths but no absolute source path', function (): void {
    $directory = sg32Directory();
    $reportDirectory = storage_path('framework/testing/sg32-reports-'.str()->random(10));
    File::ensureDirectoryExists($reportDirectory);
    $report = $reportDirectory.'/preflight.json';

    try {
        $this->artisan('archive:batch-preflight', ['directory' => $directory, '--json' => $report])->assertFailed();
        $contents = File::get($report);
        expect($contents)->toContain('corrupt.jpg')
            ->and($contents)->toContain('unreadable_image')
            ->and($contents)->not->toContain(str_replace('\\', '/', $directory));
    } finally {
        File::deleteDirectory($directory);
        File::deleteDirectory($reportDirectory);
    }
});

it('isolates unreadable photos when planning and never persists source paths', function (): void {
    $owner = User::factory()->create(['role' => 'owner', 'email_verified_at' => now()]);
    $directory = sg32Directory();

    try {
        $planned = app(HighVolumePhotoBatch::class)->plan($owner, $directory, 25);
        $manifest = (string) DB::table('cloud_import_sessions')->where('session_id', $planned['session_id'])->value('source_manifest');
        $failed = DB::table('cloud_import_items')->where('state', 'failed')->first();

        expect($planned['selected_count'])->toBe(3)
            ->and($failed)->not->toBeNull()
            ->and($failed->failure_code)->toBe('unreadable_image')
            ->and($manifest)->toContain('preflight_summary')
            ->and($manifest)->not->toContain(str_replace('\\', '/', $directory));
    } finally {
        File::deleteDirectory($directory);
    }
});

it('retries only transient failures and can finish unattended', function (): void {
    Storage::fake('archive_quarantine');
    $owner = User::factory()->create(['role' => 'owner', 'email_verified_at' => now()]);
    $directory = storage_path('framework/testing/sg32-run-'.str()->random(10));
    File::ensureDirectoryExists($directory);
    foreach (range(1, 3) as $position) {
        $photo = UploadedFile::fake()->image("photo-{$position}.jpg", 120, 80);
        File::copy($photo->getRealPath(), $directory."/photo-{$position}.jpg");
    }

    try {
        $batches = app(HighVolumePhotoBatch::class);
        $planned = $batches->plan($owner, $directory, 25);
        $sessionKey = DB::table('cloud_import_sessions')->where('session_id', $planned['session_id'])->value('id');
        $failedId = DB::table('cloud_import_items')->where('cloud_import_session_id', $sessionKey)->orderBy('position')->value('id');
        DB::table('cloud_import_items')->where('id', $failedId)->update(['state' => 'failed', 'failure_code' => 'retention_failed', 'attempt_count' => 1]);

        expect($batches->retryFailed($planned['session_id'], 10))->toBe(1);
        $result = $batches->runToCompletion($planned['session_id'], $directory);
        expect($result)->toMatchArray(['state' => 'complete', 'retained_count' => 3, 'failed_count' => 0, 'remaining_count' => 0])
            ->and(Storage::disk('archive_quarantine')->allFiles())->toHaveCount(3);
    } finally {
        File::deleteDirectory($directory);
    }
});
