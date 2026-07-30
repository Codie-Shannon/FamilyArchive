<?php

use App\Domain\Duplicates\Services\PerceptualSimilarity;
use App\Domain\Media\Enums\MediaFileVersionType;
use App\Domain\Media\Models\MediaFileVersion;
use App\Domain\Metadata\Services\MetadataMergePreview;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

it('creates advanced media intelligence records', function () {
    foreach ([
        'media_perceptual_fingerprints',
        'visual_similarity_candidates',
        'alternate_media_sources',
        'metadata_merge_proposals',
    ] as $table) {
        expect(Schema::hasTable($table))->toBeTrue();
    }
});

it('calculates deterministic perceptual distance and records review candidates', function () {
    $left = MediaFileVersion::factory()->create(['version_type' => MediaFileVersionType::Original]);
    $right = MediaFileVersion::factory()->create(['version_type' => MediaFileVersionType::Original]);
    $service = app(PerceptualSimilarity::class);

    expect($service->distance('0000000000000000', '000000000000000f'))->toBe(4);

    $id = $service->recordCandidate(
        $left->id,
        $right->id,
        '0000000000000000',
        '000000000000000f',
    );

    expect(DB::table('visual_similarity_candidates')->where('candidate_id', $id)->value('review_state'))
        ->toBe('pending');
});

it('rejects malformed fingerprints and self-comparison', function () {
    $version = MediaFileVersion::factory()->create(['version_type' => MediaFileVersionType::Original]);
    $service = app(PerceptualSimilarity::class);

    expect(fn () => $service->distance('not-a-hash', '0000000000000000'))
        ->toThrow(ValidationException::class)
        ->and(fn () => $service->recordCandidate(
            $version->id,
            $version->id,
            '0000000000000000',
            '0000000000000000',
        ))
        ->toThrow(ValidationException::class);
});

it('previews metadata conflicts instead of silently merging them', function () {
    $preview = app(MetadataMergePreview::class)->preview(
        ['title' => 'Reviewed title', 'location' => null],
        ['title' => 'Different title', 'location' => 'Fictional Wellington'],
    );

    expect($preview['decisions']['location'])->toBe('Fictional Wellington')
        ->and($preview['conflicts']['title'])->toBe([
            'target' => 'Reviewed title',
            'source' => 'Different title',
        ]);
});

it('keeps the intelligence workspace inside the verified owner boundary', function () {
    $this->withoutVite();

    $owner = User::factory()->create(['role' => 'owner', 'email_verified_at' => now()]);
    $viewer = User::factory()->create(['role' => 'viewer', 'email_verified_at' => now()]);

    $this->get(route('admin.media-intelligence'))->assertRedirect('/login');
    $this->actingAs($viewer)->get(route('admin.media-intelligence'))->assertForbidden();
    $this->actingAs($owner)
        ->get(route('admin.media-intelligence'))
        ->assertOk()
        ->assertSee('Advanced media intelligence')
        ->assertSee('Similarity is candidate-only')
        ->assertDontSee('fingerprint')
        ->assertDontSee('source_version_id');
});

it('keeps v1.1 release metadata aligned', function () {
    expect(config('release.version'))->toBe('1.1.0')
        ->and(config('release.name'))->toBe('Advanced Media Intelligence')
        ->and(config('release.groups'))->toBe('POST-V1-A')
        ->and(config('release.status'))->toBe('Screenshot Group 06 evidence closed');
});
