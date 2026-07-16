<?php

use App\Models\User;
use Illuminate\Support\Facades\Storage;

it('does not invoke any filesystem disk while rendering the overview', function (): void {
    Storage::fake('archive_originals');
    Storage::fake('archive_derivatives');
    Storage::fake('archive_quarantine');
    Storage::fake('archive_manifests');

    $owner = User::factory()->create(['role' => 'owner', 'email_verified_at' => now()]);
    $this->actingAs($owner)->get(route('admin.archive-storage'))->assertOk();

    foreach (['archive_originals', 'archive_derivatives', 'archive_quarantine', 'archive_manifests'] as $disk) {
        expect(Storage::disk($disk)->allFiles())->toBe([]);
    }
});

it('contains no forbidden storage mutation calls in group 03 domain services', function (): void {
    $files = glob(app_path('Domain/Archive/Services/*.php')) ?: [];
    $source = collect($files)->map(fn (string $file): string => file_get_contents($file) ?: '')->implode("\n");

    foreach (['Storage::put', 'writeStream', 'Storage::copy', 'Storage::move', 'Storage::delete', 'temporaryUrl', 'url('] as $forbidden) {
        expect($source)->not->toContain($forbidden);
    }
});
