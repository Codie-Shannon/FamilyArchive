<?php

use App\Domain\Access\Models\ContributorSubmission;
use App\Domain\CloudImport\Services\HighVolumePhotoBatch;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

function sg20Directory(int $files = 3): string
{
    $directory = storage_path('framework/testing/sg20-'.str()->random(10));
    File::ensureDirectoryExists($directory);
    foreach (range(1, $files) as $position) {
        $photo = UploadedFile::fake()->image("fictional-{$position}.jpg", 80, 60);
        File::copy($photo->getRealPath(), $directory."/fictional-{$position}.jpg");
    }

    return $directory;
}

it('inventories and resumes a large photo batch through bounded checkpoints', function (): void {
    Storage::fake('archive_quarantine');
    $owner = User::factory()->create(['role' => 'owner', 'email_verified_at' => now()]);
    $directory = sg20Directory();

    try {
        $planned = app(HighVolumePhotoBatch::class)->plan($owner, $directory, 25);
        expect($planned['selected_count'])->toBe(3)
            ->and(DB::table('cloud_import_items')->count())->toBe(3)
            ->and(DB::table('cloud_import_sessions')->value('source_manifest'))->not->toContain(str_replace('\\', '/', $directory));

        $first = app(HighVolumePhotoBatch::class)->process($planned['session_id'], $directory, 2);
        expect($first)->toMatchArray(['state' => 'paused', 'retained_count' => 2, 'failed_count' => 0, 'remaining_count' => 1]);

        $second = app(HighVolumePhotoBatch::class)->process($planned['session_id'], $directory, 2);
        $submissions = ContributorSubmission::query()->where('status', 'retained')->get();
        expect($second)->toMatchArray(['state' => 'complete', 'retained_count' => 3, 'failed_count' => 0, 'remaining_count' => 0])
            ->and($submissions)->toHaveCount(3)
            ->and($submissions->every(fn (ContributorSubmission $submission): bool => $submission->automation_preferences === [
                'automation_mode' => 'candidates',
                'crop_target' => 'photo_edge',
                'auto_rotate' => true,
                'deskew' => true,
            ]))->toBeTrue()
            ->and(Storage::disk('archive_quarantine')->allFiles())->toHaveCount(3);
    } finally {
        File::deleteDirectory($directory);
    }
});

it('fails closed when the source inventory changes after planning', function (): void {
    Storage::fake('archive_quarantine');
    $owner = User::factory()->create(['role' => 'owner']);
    $directory = sg20Directory(1);

    try {
        $planned = app(HighVolumePhotoBatch::class)->plan($owner, $directory, 25);
        $extra = UploadedFile::fake()->image('fictional-extra.jpg', 80, 60);
        File::copy($extra->getRealPath(), $directory.'/fictional-extra.jpg');

        expect(fn () => app(HighVolumePhotoBatch::class)->process($planned['session_id'], $directory, 1))
            ->toThrow(RuntimeException::class, 'inventory changed');
        expect(Storage::disk('archive_quarantine')->allFiles())->toBe([]);
    } finally {
        File::deleteDirectory($directory);
    }
});

it('keeps the high-volume progress view owner only and path safe', function (): void {
    $this->withoutVite();
    $owner = User::factory()->create(['role' => 'owner', 'email_verified_at' => now()]);
    $member = User::factory()->create(['role' => 'member', 'email_verified_at' => now()]);
    $directory = sg20Directory(1);

    try {
        $planned = app(HighVolumePhotoBatch::class)->plan($owner, $directory, 25);
        $this->get(route('admin.batch-imports'))->assertRedirect('/login');
        $this->actingAs($member)->get(route('admin.batch-imports'))->assertForbidden();
        $this->actingAs($owner)->get(route('admin.batch-imports'))->assertOk()
            ->assertSee('High-volume photo batches')
            ->assertSee('No source path enters the database')
            ->assertDontSee(str_replace('\\', '/', $directory));
        $this->actingAs($owner)->get(route('admin.batch-imports', ['batch' => $planned['session_id']]))->assertOk()
            ->assertSee($planned['session_id']);
    } finally {
        File::deleteDirectory($directory);
    }
});
