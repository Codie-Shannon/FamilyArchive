<?php

use App\Domain\Media\Enums\MediaFileVersionType;
use App\Domain\Media\Models\MediaFileVersion;
use App\Domain\Processing\Services\RestorationWorkflow;
use App\Domain\Storage\Services\ArchiveProviderConfiguration;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

it('creates collaboration and restoration records', function () {
    foreach ([
        'identity_suggestions',
        'archive_notifications',
        'processing_recipes',
        'processing_jobs',
        'restoration_candidates',
        'storage_provider_statuses',
    ] as $table) {
        expect(Schema::hasTable($table))->toBeTrue();
    }
});

it('versions restoration recipes and queues only preferred originals', function () {
    $workflow = app(RestorationWorkflow::class);
    $recipe = $workflow->createRecipe('Fictional gentle restoration', 1, [
        'deskew' => ['max_degrees' => 4],
        'colour' => ['mode' => 'neutral'],
    ]);
    $source = MediaFileVersion::factory()->create([
        'version_type' => MediaFileVersionType::Original,
        'is_preferred' => true,
    ]);
    $before = $source->only(['storage_disk', 'storage_path', 'sha256', 'is_preferred']);
    $versionCount = MediaFileVersion::count();

    expect($workflow->queue($source, $recipe))->toBeString()
        ->and(DB::table('processing_jobs')->value('state'))->toBe('queued')
        ->and($source->fresh()->only(['storage_disk', 'storage_path', 'sha256', 'is_preferred']))->toBe($before)
        ->and($source->fresh()->version_type)->toBe(MediaFileVersionType::Original)
        ->and(MediaFileVersion::count())->toBe($versionCount);
});

it('rejects a derivative as a restoration source', function () {
    $recipe = app(RestorationWorkflow::class)->createRecipe('Fictional cleanup', 1, [
        'surface_cleanup' => ['strength' => 'low'],
    ]);
    $derivative = MediaFileVersion::factory()->create([
        'version_type' => MediaFileVersionType::WebDisplay,
        'is_preferred' => true,
    ]);

    expect(fn () => app(RestorationWorkflow::class)->queue($derivative, $recipe))
        ->toThrow(ValidationException::class);
});

it('rejects unsupported or empty processing operations', function () {
    expect(fn () => app(RestorationWorkflow::class)->createRecipe('Unsafe', 1, ['overwrite_original' => true]))
        ->toThrow(ValidationException::class)
        ->and(fn () => app(RestorationWorkflow::class)->createRecipe('Empty', 1, []))
        ->toThrow(ValidationException::class);
});

it('fails closed when wasabi secrets are absent', function () {
    $status = app(ArchiveProviderConfiguration::class)->status('wasabi');

    expect($status['configured'])->toBeFalse()
        ->and($status['visibility'])->toBe('private')
        ->and($status['missing'])->toContain('secret', 'bucket', 'endpoint');
});

it('keeps the restoration workspace inside the verified owner boundary', function () {
    $this->withoutVite();

    $owner = User::factory()->create(['role' => 'owner', 'email_verified_at' => now()]);
    $viewer = User::factory()->create(['role' => 'viewer', 'email_verified_at' => now()]);

    $this->get(route('admin.restoration'))->assertRedirect('/login');
    $this->actingAs($viewer)->get(route('admin.restoration'))->assertForbidden();
    $this->actingAs($owner)
        ->get(route('admin.restoration'))
        ->assertOk()
        ->assertSee('Collaboration and restoration')
        ->assertSee('External configuration required')
        ->assertDontSee('WASABI_SECRET_ACCESS_KEY');
});

it('reports the Screenshot Group 03 release boundary', function () {
    expect(config('release.version'))->toBe('0.36.0')
        ->and(config('release.name'))->toBe('Collaboration & Restoration')
        ->and(config('release.groups'))->toBe('29-36')
        ->and(config('release.status'))->toBe('Screenshot Group 03 implemented — evidence pending');
});
