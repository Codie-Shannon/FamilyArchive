<?php

use App\Domain\Intake\Models\IncomingUpload;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);
function fictionalPng(): UploadedFile
{
    $bytes = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9Y9Z8ioAAAAASUVORK5CYII=');
    $path = tempnam(sys_get_temp_dir(), 'g04');
    file_put_contents($path, $bytes);

    return new UploadedFile($path, 'fictional-grid.png', 'image/png', null, true);
}
it('enforces owner access', function () {
    $owner = User::factory()->create(['role' => 'owner', 'email_verified_at' => now()]);
    $viewer = User::factory()->create(['role' => 'viewer', 'email_verified_at' => now()]);
    $this->get('/admin/photo-intake')->assertRedirect('/login');
    $this->actingAs($viewer)->get('/admin/photo-intake')->assertForbidden();
    $this->actingAs($owner)->get('/admin/photo-intake')->assertOk()->assertSee('No-retention boundary');
});
it('creates exactly one validated photo record through the controlled retention boundary', function () {
    foreach (['archive_originals', 'archive_derivatives', 'archive_quarantine', 'archive_manifests'] as $d) {
        Storage::fake($d);
    } $owner = User::factory()->create(['role' => 'owner', 'email_verified_at' => now()]);
    $this->actingAs($owner)->post('/admin/photo-intake', ['photo' => fictionalPng()])->assertRedirect();
    expect(IncomingUpload::count())->toBe(1);
    $u = IncomingUpload::first();
    expect($u->upload_id)->toStartWith('UP_')->and($u->sha256)->toMatch('/^[a-f0-9]{64}$/')->and($u->media_item_id)->toBeNull()->and($u->source_file_retained)->toBeTrue()->and($u->incoming_path)->toStartWith('incoming/');
    expect(Storage::disk('archive_quarantine')->allFiles())->toHaveCount(1);
    foreach (['archive_originals', 'archive_derivatives', 'archive_manifests'] as $d) {
        expect(Storage::disk($d)->allFiles())->toBe([]);
    }
});
it('rejects unsupported input without a record or storage residue', function () {
    Storage::fake('archive_quarantine');
    $owner = User::factory()->create(['role' => 'owner', 'email_verified_at' => now()]);
    $bad = UploadedFile::fake()->createWithContent('fictional.pdf', 'not an image');
    $this->actingAs($owner)->post('/admin/photo-intake', ['photo' => $bad])->assertSessionHasErrors('photo');
    expect(IncomingUpload::count())->toBe(0)->and(Storage::disk('archive_quarantine')->allFiles())->toBe([]);
});
it('renders read only queue and detail', function () {
    $owner = User::factory()->create(['role' => 'owner', 'email_verified_at' => now()]);
    $u = IncomingUpload::factory()->create(['uploader_id' => $owner->id]);
    $this->actingAs($owner)->get('/admin/incoming-uploads')->assertOk()->assertSee($u->upload_id)->assertDontSee('Delete');
    $this->actingAs($owner)->get('/admin/incoming-uploads/'.$u->id)->assertOk()->assertSee('retained=')->assertSee('SHA-256:')->assertDontSee('Download');
});
