<?php

use App\Domain\CloudImport\Services\HighVolumePhotoBatch;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Storage::fake('archive_quarantine');
});

it('attests an exact source SHA set without persisting excluded paths and replays idempotently', function (): void {
    $owner = User::factory()->create([
        'role' => 'owner',
        'email' => 'lineage-owner@example.test',
        'email_verified_at' => now(),
    ]);
    $directory = storage_path('framework/testing/source-lineage-'.str()->random(10));
    File::ensureDirectoryExists($directory);
    $first = UploadedFile::fake()->image('first.jpg', 100, 80);
    $second = UploadedFile::fake()->image('second.jpg', 120, 90);
    File::copy($first->getRealPath(), $directory.'/first.jpg');
    File::copy($second->getRealPath(), $directory.'/second.jpg');

    try {
        $batch = app(HighVolumePhotoBatch::class);
        $planned = $batch->plan($owner, $directory, 25);
        $batch->process($planned['session_id'], $directory, 2);
        $session = DB::table('cloud_import_sessions')->where('session_id', $planned['session_id'])->firstOrFail();
        $hashes = DB::table('cloud_import_items as item')
            ->join('incoming_uploads as upload', 'upload.id', '=', 'item.incoming_upload_id')
            ->where('item.cloud_import_session_id', $session->id)
            ->pluck('upload.sha256')
            ->map(fn ($sha): string => strtolower((string) $sha))
            ->unique()
            ->sort()
            ->values();
        $sourceSetSha256 = hash('sha256', $hashes->implode("\n")."\n");
        $engine = require base_path('tools/family_photo_source_lineage_attest.php');
        $payload = [
            'session_id' => $planned['session_id'],
            'owner_email' => $owner->email,
            'expected_inventory_sha256' => $planned['inventory_sha256'],
            'expected_source_count' => 2,
            'current_source_set_sha256' => str_repeat('0', 64),
            'lineage_proof_sha256' => str_repeat('1', 64),
            'census_sha256' => str_repeat('2', 64),
            'source_preflight_sha256' => str_repeat('3', 64),
            'source_ledger_sha256' => str_repeat('4', 64),
            'original_inventory_sha256' => str_repeat('5', 64),
            'original_unique_count' => 3,
            'missing_unique_count' => 1,
            'excluded_subtree_count' => 1,
            'exclusion_policy_fingerprint' => str_repeat('6', 64),
            'exclusion_enforcement' => 'pruned_before_discovery',
        ];
        $manifestBefore = DB::table('cloud_import_sessions')->where('id', $session->id)->value('source_manifest');
        expect(fn () => $engine($payload))->toThrow(RuntimeException::class, 'source SHA set')
            ->and(DB::table('cloud_import_sessions')->where('id', $session->id)->value('source_manifest'))->toBe($manifestBefore);

        $payload['current_source_set_sha256'] = $sourceSetSha256;
        $firstResult = $engine($payload);
        $manifestAfter = DB::table('cloud_import_sessions')->where('id', $session->id)->value('source_manifest');
        $replay = $engine($payload);
        $decoded = json_decode((string) $manifestAfter, true, 512, JSON_THROW_ON_ERROR);

        expect($firstResult['updated'])->toBeTrue()
            ->and($replay['updated'])->toBeFalse()
            ->and($decoded['preflight_summary'])->toMatchArray([
                'excluded_subtree_count' => 1,
                'exclusion_policy_fingerprint' => str_repeat('6', 64),
                'exclusion_enforcement' => 'pruned_before_discovery',
                'excluded_paths_persisted' => false,
            ])
            ->and($decoded['source_lineage_attestation'])->toMatchArray([
                'current_source_set_sha256' => $sourceSetSha256,
                'source_ledger_sha256' => str_repeat('4', 64),
                'original_unique_count' => 3,
                'current_unique_count' => 2,
                'missing_unique_count' => 1,
                'excluded_paths_persisted' => false,
            ])
            ->and($manifestAfter)->not->toContain($directory)
            ->and(DB::table('cloud_import_sessions')->where('id', $session->id)->value('source_manifest'))->toBe($manifestAfter);
    } finally {
        File::deleteDirectory($directory);
    }
});
