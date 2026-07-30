<?php

use App\Domain\Integrity\Services\VerifiedTransfer;
use App\Domain\Integrity\Services\VerifyStoredVersion;
use App\Domain\Media\Models\MediaFileVersion;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

it('creates the integrity and operations records', function () {
    foreach ([
        'storage_transfers',
        'integrity_manifests',
        'integrity_checks',
        'repair_cases',
        'scan_imports',
        'backup_verifications',
        'operational_events',
    ] as $table) {
        expect(Schema::hasTable($table))->toBeTrue();
    }
});

it('copies only after source and destination verification', function () {
    Storage::fake('source');
    Storage::fake('target');
    $bytes = 'fictional verified archive object';
    Storage::disk('source')->put('originals/item.bin', $bytes);

    $result = app(VerifiedTransfer::class)->copy(
        Storage::disk('source'),
        Storage::disk('target'),
        'originals/item.bin',
        'verified/item.bin',
        hash('sha256', $bytes),
    );

    expect($result['bytes'])->toBe(strlen($bytes))
        ->and($result['sha256'])->toBe(hash('sha256', $bytes))
        ->and(Storage::disk('source')->get('originals/item.bin'))->toBe($bytes)
        ->and(Storage::disk('target')->get('verified/item.bin'))->toBe($bytes);
});

it('refuses to overwrite an existing target', function () {
    Storage::fake('source');
    Storage::fake('target');
    Storage::disk('source')->put('source.bin', 'source');
    Storage::disk('target')->put('target.bin', 'existing');

    expect(fn () => app(VerifiedTransfer::class)->copy(
        Storage::disk('source'),
        Storage::disk('target'),
        'source.bin',
        'target.bin',
        hash('sha256', 'source'),
    ))->toThrow(ValidationException::class)
        ->and(Storage::disk('source')->get('source.bin'))->toBe('source')
        ->and(Storage::disk('target')->get('target.bin'))->toBe('existing');
});

it('refuses a source identity mismatch without writing a target', function () {
    Storage::fake('source');
    Storage::fake('target');
    Storage::disk('source')->put('source.bin', 'changed');

    expect(fn () => app(VerifiedTransfer::class)->copy(
        Storage::disk('source'),
        Storage::disk('target'),
        'source.bin',
        'target.bin',
        hash('sha256', 'expected'),
    ))->toThrow(ValidationException::class)
        ->and(Storage::disk('source')->get('source.bin'))->toBe('changed')
        ->and(Storage::disk('target')->exists('target.bin'))->toBeFalse();
});

it('records integrity mismatches without mutating the object', function () {
    Storage::fake('check');
    Storage::disk('check')->put('demo/object.bin', 'changed');
    $version = MediaFileVersion::factory()->create([
        'storage_path' => 'demo/object.bin',
        'file_size_bytes' => 7,
        'sha256' => hash('sha256', 'expected'),
    ]);

    expect(app(VerifyStoredVersion::class)->check($version, Storage::disk('check')))->toBe('hash_mismatch')
        ->and(Storage::disk('check')->get('demo/object.bin'))->toBe('changed')
        ->and(DB::table('integrity_checks')->value('result'))->toBe('hash_mismatch')
        ->and(MediaFileVersion::count())->toBe(1);
});

it('keeps operations inside the verified owner boundary', function () {
    $this->withoutVite();

    $owner = User::factory()->create(['role' => 'owner', 'email_verified_at' => now()]);
    $viewer = User::factory()->create(['role' => 'viewer', 'email_verified_at' => now()]);

    $this->get(route('admin.operations'))->assertRedirect('/login');
    $this->actingAs($viewer)->get(route('admin.operations'))->assertForbidden();
    $this->actingAs($owner)
        ->get(route('admin.operations'))
        ->assertOk()
        ->assertSee('Integrity and production operations')
        ->assertSee('No overwrite')
        ->assertSee('not production restore proof')
        ->assertDontSee('AWS_SECRET_ACCESS_KEY')
        ->assertDontSee('WASABI_SECRET_ACCESS_KEY');
});

it('reports the Screenshot Group 04 release boundary', function () {
    expect(config('release.version'))->toBe('0.44.0')
        ->and(config('release.name'))->toBe('Integrity & Production')
        ->and(config('release.groups'))->toBe('37-44')
        ->and(config('release.status'))->toBe('Screenshot Group 04 implemented — evidence pending');
});
