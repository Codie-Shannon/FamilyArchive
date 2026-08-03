<?php

use App\Domain\CloudImport\Services\HighVolumePhotoBatch;
use App\Domain\CloudImport\Services\PhotoBatchPreflight;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

function sg35Source(): array
{
    $root = storage_path('framework/testing/sg35-'.str()->random(10));
    $excluded = $root.'/excluded-private-subtree';
    File::ensureDirectoryExists($excluded.'/nested');
    $photo = UploadedFile::fake()->image('included.jpg', 100, 80);
    File::copy($photo->getRealPath(), $root.'/included.jpg');
    File::put($excluded.'/must-not-be-read.jpg', 'invalid image bytes');
    File::put($excluded.'/nested/must-not-appear.txt', 'private marker');

    return [$root, $excluded];
}

it('prunes excluded subtrees before image analysis or evidence generation', function (): void {
    [$root, $excluded] = sg35Source();

    try {
        $result = app(PhotoBatchPreflight::class)->scan($root, true, [$excluded]);
        $encoded = json_encode($result, JSON_THROW_ON_ERROR);

        expect($result['summary'])->toMatchArray([
            'supported_count' => 1,
            'valid_count' => 1,
            'invalid_count' => 0,
            'ignored_count' => 0,
            'excluded_subtree_count' => 1,
            'excluded_paths_persisted' => false,
            'exclusion_enforcement' => 'pruned_before_discovery',
        ])->and($encoded)->not->toContain('excluded-private-subtree')
            ->and($encoded)->not->toContain('must-not-be-read')
            ->and($encoded)->not->toContain('must-not-appear');
    } finally {
        File::deleteDirectory($root);
    }
});

it('rejects missing outside-root and source-root exclusions before discovery', function (): void {
    [$root, $excluded] = sg35Source();
    $outside = storage_path('framework/testing/sg35-outside-'.str()->random(10));
    File::ensureDirectoryExists($outside);

    try {
        expect(fn () => app(PhotoBatchPreflight::class)->scan($root, true, [$root.'/missing']))
            ->toThrow(RuntimeException::class, 'does not exist');
        expect(fn () => app(PhotoBatchPreflight::class)->scan($root, true, [$outside]))
            ->toThrow(RuntimeException::class, 'strict descendant');
        expect(fn () => app(PhotoBatchPreflight::class)->scan($root, true, [$root]))
            ->toThrow(RuntimeException::class, 'strict descendant');
    } finally {
        File::deleteDirectory($root);
        File::deleteDirectory($outside);
    }
});

it('stores only a keyed policy fingerprint and requires the same boundary to resume', function (): void {
    Storage::fake('archive_quarantine');
    $owner = User::factory()->create(['role' => 'owner', 'email_verified_at' => now()]);
    [$root, $excluded] = sg35Source();

    try {
        $batches = app(HighVolumePhotoBatch::class);
        $planned = $batches->plan($owner, $root, 25, [$excluded]);
        $manifest = (string) DB::table('cloud_import_sessions')->where('session_id', $planned['session_id'])->value('source_manifest');

        expect($manifest)->toContain('exclusion_policy_fingerprint')
            ->and($manifest)->toContain('pruned_before_discovery')
            ->and($manifest)->not->toContain('excluded-private-subtree')
            ->and($manifest)->not->toContain(str_replace('\\', '/', $root));

        expect(fn () => $batches->process($planned['session_id'], $root, 1))
            ->toThrow(RuntimeException::class, 'exclusion policy changed');
        expect(Storage::disk('archive_quarantine')->allFiles())->toBe([]);

        $result = $batches->process($planned['session_id'], $root, 1, [$excluded]);
        expect($result)->toMatchArray(['state' => 'complete', 'retained_count' => 1, 'failed_count' => 0, 'remaining_count' => 0])
            ->and(Storage::disk('archive_quarantine')->allFiles())->toHaveCount(1);
    } finally {
        File::deleteDirectory($root);
    }
});

it('accepts repeatable relative exclusions in operator commands', function (): void {
    [$root] = sg35Source();

    try {
        $this->artisan('archive:batch-preflight', [
            'directory' => $root,
            '--exclude' => ['excluded-private-subtree'],
        ])->expectsOutputToContain('Excluded source subtrees')->assertSuccessful();
    } finally {
        File::deleteDirectory($root);
    }
});

it('shows the owner a path-safe exclusion boundary without exposing excluded names', function (): void {
    $this->withoutVite();
    $owner = User::factory()->create(['role' => 'owner', 'email_verified_at' => now()]);
    [$root, $excluded] = sg35Source();

    try {
        $planned = app(HighVolumePhotoBatch::class)->plan($owner, $root, 25, [$excluded]);

        $this->actingAs($owner)
            ->get(route('admin.batch-imports', ['batch' => $planned['session_id']]))
            ->assertOk()
            ->assertSee('Strict source exclusion')
            ->assertSee('Pruned before discovery')
            ->assertSee('No names or paths')
            ->assertSee('Policy locked for resume')
            ->assertDontSee('excluded-private-subtree')
            ->assertDontSee(str_replace('\\', '/', $root));
    } finally {
        File::deleteDirectory($root);
    }
});
