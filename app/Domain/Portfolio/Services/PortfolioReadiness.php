<?php

namespace App\Domain\Portfolio\Services;

use Illuminate\Support\Facades\DB;

final class PortfolioReadiness
{
    /** @return array<string, int> */
    public function metrics(): array
    {
        return [
            'archive_items' => DB::table('media_items')->count(),
            'verified_originals' => DB::table('media_file_versions')->where('version_type', 'original')->count(),
            'metadata_revisions' => DB::table('media_metadata_revisions')->count(),
            'public_stories' => DB::table('public_showcase_entries')->where('state', 'published')->count(),
            'integrity_checks' => DB::table('integrity_checks')->count(),
        ];
    }

    /** @return array{enabled: bool, fictional_dataset: string, read_only: bool, no_real_family_data: bool} */
    public function safeguards(): array
    {
        return [
            'enabled' => (bool) config('portfolio_demo.enabled'),
            'fictional_dataset' => (string) config('portfolio_demo.dataset'),
            'read_only' => true,
            'no_real_family_data' => true,
        ];
    }

    /** @return array<string, int> */
    public function integrityProof(): array
    {
        return [
            'preferred_originals' => DB::table('media_file_versions')
                ->where('version_type', 'original')
                ->where('is_preferred', true)
                ->count(),
            'verified_transfers' => DB::table('storage_transfers')->where('state', 'verified')->count(),
            'integrity_checks' => DB::table('integrity_checks')->count(),
            'backup_verifications' => DB::table('backup_verifications')->where('result', 'verified')->count(),
        ];
    }

    /** @return array<string, int> */
    public function privacyProof(): array
    {
        return [
            'approved_accounts' => DB::table('users')->where('account_state', 'approved')->count(),
            'original_access_grants' => DB::table('original_access_grants')->whereNull('revoked_at')->count(),
            'published_stories' => DB::table('public_showcase_entries')->where('state', 'published')->count(),
            'privacy_reviewed_maps' => DB::table('public_map_points')->where('privacy_reviewed', true)->count(),
        ];
    }
}
