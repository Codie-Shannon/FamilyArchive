<?php

use App\Domain\Metadata\Actions\UpdatePhotoMetadata;
use App\Domain\Metadata\Models\PhotoMetadataRevision;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);
it('shows owner-only immutable history and safe before after values', function () {
    $owner = g11Owner();
    $item = g11Item($owner, 'G11-HIST');
    $item->update(['title' => 'Before']);
    app(UpdatePhotoMetadata::class)->handle($item, $owner, g11MetadataPayload($item, [
        'title' => 'After',
        'change_reason' => 'Correct fictional title.',
    ]));
    $rev = PhotoMetadataRevision::firstOrFail();
    $this->get(route('archive.photos.metadata.history', $item))->assertRedirect('/login');
    $this->actingAs($owner)->get(route('archive.photos.metadata.history', $item))->assertOk()->assertSee('Revision 1');
    $this->actingAs($owner)->get(route('archive.photos.metadata.history.show', [$item, $rev]))->assertOk()->assertSee('Before')->assertSee('After')->assertDontSee('storage_path')->assertDontSee('sha256');
});
it('keeps revision rows immutable', function () {
    $owner = g11Owner();
    $item = g11Item($owner, 'G11-IMM');
    app(UpdatePhotoMetadata::class)->handle($item, $owner, g11MetadataPayload($item, [
        'title' => 'Changed',
        'change_reason' => 'Correct fictional title.',
    ]));
    $rev = PhotoMetadataRevision::firstOrFail();
    expect(fn () => $rev->update(['change_reason' => 'rewrite']))->toThrow(LogicException::class);
    expect(fn () => $rev->delete())->toThrow(LogicException::class);
});
