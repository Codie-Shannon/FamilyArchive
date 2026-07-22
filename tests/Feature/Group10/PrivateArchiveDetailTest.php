<?php

use App\Domain\Media\Enums\MediaFileVersionType;
use App\Domain\Media\Enums\MediaReviewStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('shows safe read only detail through the web display derivative only', function () {
    $owner = group10Owner();
    $item = group10Item($owner, 'G10-DETAIL');
    $item->update(['title' => 'Safe archive title', 'description' => 'Approved description']);
    $original = group10Original($item);
    $web = group10Derivative($item, $original, MediaFileVersionType::WebDisplay);
    group10Derivative($item, $original, MediaFileVersionType::Thumbnail);
    $response = $this->actingAs($owner)->get(route('archive.photos.show', $item));
    $response->assertOk()->assertSee('Safe archive title')->assertSee('Approved description')->assertSee(route('archive.derivatives.preview', $web))->assertDontSee($original->storage_path)->assertDontSee($original->sha256)->assertDontSee('Download')->assertDontSee('Delete');
});

it('fails safely for missing web display and rejects unapproved details', function () {
    $owner = group10Owner();
    $item = group10Item($owner, 'G10-MISSING');
    group10Original($item);
    $this->actingAs($owner)->get(route('archive.photos.show', $item))->assertOk()->assertSee('Web display unavailable')->assertSee('no generation side effect');
    $pending = group10Item($owner, 'G10-HIDDEN', MediaReviewStatus::PendingReview);
    $this->actingAs($owner)->get(route('archive.photos.show', $pending))->assertNotFound();
});
