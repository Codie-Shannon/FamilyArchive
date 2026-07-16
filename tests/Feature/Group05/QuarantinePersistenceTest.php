<?php

use App\Domain\Intake\Exceptions\QuarantinePersistenceException;
use App\Domain\Intake\Models\IncomingUpload;
use App\Domain\Intake\Services\CreateIncomingPhotoRecord;
use App\Domain\Intake\Services\RetainIncomingPhoto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

function group05Png(string $name = 'fictional-grid.png'): UploadedFile
{
    $bytes = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9Y9Z8ioAAAAASUVORK5CYII=');
    $path = tempnam(sys_get_temp_dir(), 'g05');
    file_put_contents($path, $bytes);

    return new UploadedFile($path, $name, 'image/png', null, true);
}

beforeEach(function () {
    foreach (['archive_originals', 'archive_derivatives', 'archive_quarantine', 'archive_manifests'] as $disk) {
        Storage::fake($disk);
    }
});

it('retains a validated photo exactly once with byte and sha256 verification', function () {
    $owner = User::factory()->create(['role' => 'owner', 'email_verified_at' => now()]);
    $this->actingAs($owner)->post('/admin/photo-intake', ['photo' => group05Png()])->assertRedirect();
    $upload = IncomingUpload::sole();
    expect($upload->source_file_retained)->toBeTrue()
        ->and($upload->retained_at)->not->toBeNull()
        ->and($upload->sha256)->toMatch('/^[a-f0-9]{64}$/')
        ->and(Storage::disk('archive_quarantine')->size($upload->incoming_path))->toBe($upload->file_size_bytes)
        ->and(hash('sha256', Storage::disk('archive_quarantine')->get($upload->incoming_path)))->toBe($upload->sha256);
    expect(Storage::disk('archive_originals')->allFiles())->toBe([])
        ->and(Storage::disk('archive_derivatives')->allFiles())->toBe([])
        ->and(Storage::disk('archive_manifests')->allFiles())->toBe([]);
});

it('fails closed on collision and preserves pre-existing bytes', function () {
    $owner = User::factory()->create(['role' => 'owner', 'email_verified_at' => now()]);
    $file = group05Png('collision.png');
    $record = app(CreateIncomingPhotoRecord::class)->create($owner, $file);
    Storage::disk('archive_quarantine')->put($record->incoming_path, 'pre-existing');
    expect(fn () => app(RetainIncomingPhoto::class)->retain($record, $file))->toThrow(QuarantinePersistenceException::class);
    expect(Storage::disk('archive_quarantine')->get($record->incoming_path))->toBe('pre-existing')
        ->and($record->fresh()->source_file_retained)->toBeFalse()
        ->and($record->fresh()->sha256)->toBeNull();
});

it('denies non owners and guests without storage access', function () {
    $viewer = User::factory()->create(['role' => 'viewer', 'email_verified_at' => now()]);
    $this->post('/admin/photo-intake', ['photo' => group05Png()])->assertRedirect('/login');
    $this->actingAs($viewer)->post('/admin/photo-intake', ['photo' => group05Png()])->assertForbidden();
    expect(Storage::disk('archive_quarantine')->allFiles())->toBe([]);
});

it('keeps queue and detail read only and exposes retention integrity', function () {
    $owner = User::factory()->create(['role' => 'owner', 'email_verified_at' => now()]);
    $this->actingAs($owner)->post('/admin/photo-intake', ['photo' => group05Png()]);
    $upload = IncomingUpload::sole();
    $this->actingAs($owner)->get('/admin/incoming-uploads')->assertOk()->assertSee('Retained')->assertSee('verified')->assertDontSee('Download');
    $this->actingAs($owner)->get('/admin/incoming-uploads/'.$upload->id)->assertOk()->assertSee($upload->sha256)->assertSee('retained=true')->assertSee('Not approved')->assertDontSee('Promote');
});
