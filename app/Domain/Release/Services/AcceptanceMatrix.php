<?php

namespace App\Domain\Release\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class AcceptanceMatrix
{
    /**
     * @return array<string, bool>
     */
    public function gates(): array
    {
        return [
            'pilot_feedback_reviewed' => DB::table('pilot_feedback')
                ->whereIn('state', ['accepted', 'resolved'])
                ->exists(),
            'no_blocking_pilot_feedback' => DB::table('pilot_feedback')
                ->where('severity', 'blocking')
                ->whereNotIn('state', ['resolved', 'declined'])
                ->doesntExist(),
            'no_open_integrity_repairs' => DB::table('repair_cases')
                ->whereNotIn('state', ['closed'])
                ->doesntExist(),
            'verified_backup_exists' => DB::table('backup_verifications')
                ->where('result', 'verified')
                ->exists(),
            'confirmed_primary_custodian' => DB::table('custodian_designations')
                ->where('role', 'primary')
                ->where('state', 'confirmed')
                ->exists(),
            'private_provider_recorded' => DB::table('storage_provider_statuses')
                ->whereIn('state', ['healthy', 'degraded'])
                ->exists(),
        ];
    }

    public function record(): string
    {
        $gates = $this->gates();
        $runId = (string) Str::uuid();

        DB::table('release_acceptance_runs')->insert([
            'run_id' => $runId,
            'version' => (string) config('release.version'),
            'state' => in_array(false, $gates, true) ? 'blocked' : 'ready',
            'gates' => json_encode($gates, JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $runId;
    }
}
