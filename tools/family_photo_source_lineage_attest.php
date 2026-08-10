<?php

return static function (array $input): array {
    $sessionId = (string) ($input['session_id'] ?? '');
    $ownerEmail = (string) ($input['owner_email'] ?? '');
    $expectedInventory = strtolower((string) ($input['expected_inventory_sha256'] ?? ''));
    $expectedSourceCount = max(0, (int) ($input['expected_source_count'] ?? 0));
    $expectedSourceSet = strtolower((string) ($input['current_source_set_sha256'] ?? ''));
    $lineageProofSha256 = strtolower((string) ($input['lineage_proof_sha256'] ?? ''));
    $censusSha256 = strtolower((string) ($input['census_sha256'] ?? ''));
    $sourcePreflightSha256 = strtolower((string) ($input['source_preflight_sha256'] ?? ''));
    $sourceLedgerSha256 = strtolower((string) ($input['source_ledger_sha256'] ?? ''));
    $originalInventorySha256 = strtolower((string) ($input['original_inventory_sha256'] ?? ''));
    $originalUniqueCount = max(0, (int) ($input['original_unique_count'] ?? 0));
    $missingUniqueCount = max(0, (int) ($input['missing_unique_count'] ?? 0));
    $excludedSubtreeCount = max(0, (int) ($input['excluded_subtree_count'] ?? 0));
    $exclusionFingerprint = strtolower((string) ($input['exclusion_policy_fingerprint'] ?? ''));
    $exclusionEnforcement = (string) ($input['exclusion_enforcement'] ?? '');

    $digests = [
        $expectedInventory,
        $expectedSourceSet,
        $lineageProofSha256,
        $censusSha256,
        $sourcePreflightSha256,
        $sourceLedgerSha256,
        $originalInventorySha256,
        $exclusionFingerprint,
    ];
    if ($sessionId === '' || $ownerEmail === '' || $expectedSourceCount < 1
        || $originalUniqueCount !== $expectedSourceCount + $missingUniqueCount
        || $excludedSubtreeCount < 1 || $exclusionEnforcement !== 'pruned_before_discovery'
        || collect($digests)->contains(fn (string $digest): bool => preg_match('/^[a-f0-9]{64}$/', $digest) !== 1)) {
        throw new InvalidArgumentException('The source-lineage attestation payload is incomplete or inconsistent.');
    }

    $actor = \App\Models\User::query()->where('email', $ownerEmail)->firstOrFail();
    if ($actor->role !== 'owner') {
        throw new RuntimeException('Only the archive owner can attest source lineage.');
    }

    return \Illuminate\Support\Facades\DB::transaction(function () use (
        $sessionId,
        $expectedInventory,
        $expectedSourceCount,
        $expectedSourceSet,
        $lineageProofSha256,
        $censusSha256,
        $sourcePreflightSha256,
        $sourceLedgerSha256,
        $originalInventorySha256,
        $originalUniqueCount,
        $missingUniqueCount,
        $excludedSubtreeCount,
        $exclusionFingerprint,
        $exclusionEnforcement,
        $actor,
    ): array {
        $session = \Illuminate\Support\Facades\DB::table('cloud_import_sessions')
            ->where('session_id', $sessionId)
            ->lockForUpdate()
            ->firstOrFail();
        if ((int) $session->selected_count !== $expectedSourceCount
            || ! hash_equals($expectedInventory, strtolower((string) $session->inventory_sha256))) {
            throw new RuntimeException('The production session no longer matches the attested source inventory.');
        }

        $hashes = \Illuminate\Support\Facades\DB::table('cloud_import_items as item')
            ->join('incoming_uploads as upload', 'upload.id', '=', 'item.incoming_upload_id')
            ->where('item.cloud_import_session_id', (int) $session->id)
            ->pluck('upload.sha256')
            ->map(fn ($sha): string => strtolower((string) $sha))
            ->unique()
            ->sort()
            ->values();
        if ($hashes->count() !== $expectedSourceCount) {
            throw new RuntimeException('The production session source SHA set is not exactly unique and complete.');
        }
        $actualSourceSet = hash('sha256', $hashes->implode("\n")."\n");
        if (! hash_equals($expectedSourceSet, $actualSourceSet)) {
            throw new RuntimeException('The production session source SHA set does not match the local lineage proof.');
        }

        $manifest = json_decode((string) $session->source_manifest, true) ?: [];
        $lineage = [
            'schema_version' => 1,
            'lineage_proof_sha256' => $lineageProofSha256,
            'census_sha256' => $censusSha256,
            'current_source_set_sha256' => $actualSourceSet,
            'source_preflight_sha256' => $sourcePreflightSha256,
            'source_ledger_sha256' => $sourceLedgerSha256,
            'original_inventory_sha256' => $originalInventorySha256,
            'original_unique_count' => $originalUniqueCount,
            'current_unique_count' => $expectedSourceCount,
            'missing_unique_count' => $missingUniqueCount,
            'excluded_subtree_count' => $excludedSubtreeCount,
            'exclusion_policy_fingerprint' => $exclusionFingerprint,
            'exclusion_enforcement' => $exclusionEnforcement,
            'excluded_paths_persisted' => false,
            'attested_by' => (int) $actor->id,
        ];
        $preflight = is_array($manifest['preflight_summary'] ?? null) ? $manifest['preflight_summary'] : [];
        $preflight['excluded_subtree_count'] = $excludedSubtreeCount;
        $preflight['exclusion_policy_fingerprint'] = $exclusionFingerprint;
        $preflight['exclusion_enforcement'] = $exclusionEnforcement;
        $preflight['excluded_paths_persisted'] = false;

        $currentLineage = is_array($manifest['source_lineage_attestation'] ?? null)
            ? $manifest['source_lineage_attestation']
            : [];
        $unchanged = $currentLineage === $lineage
            && ($manifest['preflight_summary'] ?? null) === $preflight;
        if (! $unchanged) {
            $manifest['preflight_summary'] = $preflight;
            $manifest['source_lineage_attestation'] = $lineage;
            \Illuminate\Support\Facades\DB::table('cloud_import_sessions')->where('id', (int) $session->id)->update([
                'source_manifest' => json_encode($manifest, JSON_THROW_ON_ERROR),
                'updated_at' => now(),
            ]);
        }

        return [
            'session_id' => $sessionId,
            'updated' => ! $unchanged,
            'source_count' => $expectedSourceCount,
            'current_source_set_sha256' => $actualSourceSet,
            'exclusion_policy_fingerprint' => $exclusionFingerprint,
            'excluded_subtree_count' => $excludedSubtreeCount,
            'excluded_paths_persisted' => false,
        ];
    });
};
