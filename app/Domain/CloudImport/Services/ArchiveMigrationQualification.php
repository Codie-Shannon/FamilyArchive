<?php

namespace App\Domain\CloudImport\Services;

use App\Domain\CloudImport\Models\MigrationQualificationRun;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

final class ArchiveMigrationQualification
{
    /** @return array{qualification_id:string,target_count:int,checkpoint_count:int,state:string} */
    public function qualify(
        User $operator,
        int $targetCount = 30000,
        int $chunkSize = 500,
        int $interruptAfter = 12000,
    ): array {
        $run = $this->plan($operator, $targetCount, $chunkSize);
        $failurePositions = $this->failurePositions($targetCount);
        $interruptCheckpoints = max(1, (int) ceil(min($interruptAfter, $targetCount - 1) / $chunkSize));

        $this->process($run->qualification_id, $interruptCheckpoints, $failurePositions);
        $this->process($run->qualification_id, null, $failurePositions);
        $this->recover($run->qualification_id);
        $this->proveReplaySafety($run->qualification_id, min($chunkSize, $targetCount));
        $qualified = $this->reconcile($run->qualification_id);

        return [
            'qualification_id' => $qualified->qualification_id,
            'target_count' => $qualified->target_count,
            'checkpoint_count' => $qualified->checkpoint_count,
            'state' => $qualified->state,
        ];
    }

    public function plan(User $operator, int $targetCount = 30000, int $chunkSize = 500): MigrationQualificationRun
    {
        if (! $operator->canManageTrustedIntake()) {
            throw new RuntimeException('A trusted intake operator is required to run migration qualification.');
        }
        if ($targetCount < 1000 || $targetCount > 100000) {
            throw new RuntimeException('Qualification must cover between 1,000 and 100,000 synthetic entries.');
        }
        if ($chunkSize < 25 || $chunkSize > 1000) {
            throw new RuntimeException('Qualification checkpoint size must be between 25 and 1,000.');
        }

        return DB::transaction(function () use ($operator, $targetCount, $chunkSize): MigrationQualificationRun {
            $run = MigrationQualificationRun::query()->create([
                'qualification_id' => (string) Str::uuid(),
                'user_id' => $operator->id,
                'state' => 'planned',
                'target_count' => $targetCount,
                'chunk_size' => $chunkSize,
                'manifest_sha256' => $this->manifestDigest($targetCount),
                'qualification_profile' => [
                    'scope' => 'synthetic_manifest_qualification',
                    'source_media_retained' => false,
                    'source_paths_persisted' => false,
                    'exercises' => ['manifest scale', 'checkpoint persistence', 'interruption', 'resume', 'failure recovery', 'idempotent replay', 'reconciliation'],
                    'real_private_migration_required' => true,
                ],
            ]);

            foreach (array_chunk(range(1, $targetCount), 500) as $positions) {
                DB::table('migration_qualification_items')->insert(array_map(fn (int $position): array => [
                    'migration_qualification_run_id' => $run->id,
                    'position' => $position,
                    'fingerprint' => $this->fingerprint($position),
                    'state' => 'pending',
                    'attempt_count' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ], $positions));
            }

            return $run->refresh();
        });
    }

    /** @param list<int> $failurePositions */
    public function process(string $qualificationId, ?int $maximumCheckpoints = null, array $failurePositions = []): MigrationQualificationRun
    {
        $run = MigrationQualificationRun::query()->where('qualification_id', $qualificationId)->firstOrFail();
        if (in_array($run->state, ['qualified', 'failed'], true)) {
            return $run;
        }

        $wasInterrupted = $run->state === 'interrupted';
        $run->update([
            'state' => 'running',
            'started_at' => $run->started_at ?? now(),
            'restart_count' => $run->restart_count + ($wasInterrupted ? 1 : 0),
        ]);

        $processedCheckpoints = 0;
        while ($maximumCheckpoints === null || $processedCheckpoints < $maximumCheckpoints) {
            $items = DB::table('migration_qualification_items')
                ->where('migration_qualification_run_id', $run->id)
                ->where('state', 'pending')
                ->orderBy('position')
                ->limit($run->chunk_size)
                ->get();
            if ($items->isEmpty()) {
                break;
            }

            $checkpointNumber = (int) floor(((int) $items->first()->position - 1) / $run->chunk_size) + 1;
            $failedIds = $items->filter(fn (object $item): bool => in_array((int) $item->position, $failurePositions, true))->pluck('id');
            $processedIds = $items->pluck('id')->diff($failedIds);
            $checkpointDigest = hash('sha256', $items->pluck('fingerprint')->implode('|'));

            DB::transaction(function () use ($run, $items, $checkpointNumber, $failedIds, $processedIds, $checkpointDigest): void {
                if ($processedIds->isNotEmpty()) {
                    DB::table('migration_qualification_items')->whereIn('id', $processedIds)->update([
                        'state' => 'processed', 'attempt_count' => 1, 'checkpoint_number' => $checkpointNumber, 'updated_at' => now(),
                    ]);
                }
                if ($failedIds->isNotEmpty()) {
                    DB::table('migration_qualification_items')->whereIn('id', $failedIds)->update([
                        'state' => 'failed', 'attempt_count' => 1, 'checkpoint_number' => $checkpointNumber, 'updated_at' => now(),
                    ]);
                }
                DB::table('migration_qualification_checkpoints')->insertOrIgnore([
                    'migration_qualification_run_id' => $run->id,
                    'checkpoint_number' => $checkpointNumber,
                    'first_position' => (int) $items->first()->position,
                    'last_position' => (int) $items->last()->position,
                    'item_count' => $items->count(),
                    'exception_count' => $failedIds->count(),
                    'checkpoint_sha256' => $checkpointDigest,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });
            $processedCheckpoints++;
        }

        return $this->refreshProgress($run, DB::table('migration_qualification_items')
            ->where('migration_qualification_run_id', $run->id)->where('state', 'pending')->exists() ? 'interrupted' : 'reconciling');
    }

    public function recover(string $qualificationId): MigrationQualificationRun
    {
        $run = MigrationQualificationRun::query()->where('qualification_id', $qualificationId)->firstOrFail();
        $recovered = DB::table('migration_qualification_items')
            ->where('migration_qualification_run_id', $run->id)
            ->where('state', 'failed')
            ->update(['state' => 'recovered', 'attempt_count' => DB::raw('attempt_count + 1'), 'updated_at' => now()]);
        $run->update(['recovered_failures' => $run->recovered_failures + $recovered]);

        return $this->refreshProgress($run->refresh(), 'reconciling');
    }

    public function proveReplaySafety(string $qualificationId, int $sampleSize = 500): MigrationQualificationRun
    {
        $run = MigrationQualificationRun::query()->where('qualification_id', $qualificationId)->firstOrFail();
        $sample = DB::table('migration_qualification_items')->where('migration_qualification_run_id', $run->id)
            ->orderBy('position')->limit(max(1, min($sampleSize, 1000)))->get();
        $inserted = DB::table('migration_qualification_items')->insertOrIgnore($sample->map(fn (object $item): array => [
            'migration_qualification_run_id' => $run->id,
            'position' => $item->position,
            'fingerprint' => $item->fingerprint,
            'state' => $item->state,
            'attempt_count' => $item->attempt_count,
            'checkpoint_number' => $item->checkpoint_number,
            'created_at' => now(),
            'updated_at' => now(),
        ])->all());
        $run->update(['duplicate_skips' => $run->duplicate_skips + ($sample->count() - $inserted)]);

        return $run->refresh();
    }

    public function reconcile(string $qualificationId): MigrationQualificationRun
    {
        $run = MigrationQualificationRun::query()->where('qualification_id', $qualificationId)->firstOrFail();
        $items = DB::table('migration_qualification_items')->where('migration_qualification_run_id', $run->id)->orderBy('position')->get();
        $digest = $this->digestFingerprints($items->pluck('fingerprint')->all());
        $positions = $items->pluck('position');
        $checkpointCount = DB::table('migration_qualification_checkpoints')->where('migration_qualification_run_id', $run->id)->count();
        $qualified = $items->count() === $run->target_count
            && $positions->unique()->count() === $run->target_count
            && (int) $positions->min() === 1
            && (int) $positions->max() === $run->target_count
            && $items->whereNotIn('state', ['processed', 'recovered'])->isEmpty()
            && hash_equals($run->manifest_sha256, $digest)
            && $checkpointCount === (int) ceil($run->target_count / $run->chunk_size);

        $profile = $run->qualification_profile;
        $profile['reconciliation'] = [
            'expected' => $run->target_count,
            'observed' => $items->count(),
            'missing' => max(0, $run->target_count - $items->count()),
            'unexpected' => max(0, $items->count() - $run->target_count),
            'manifest_match' => hash_equals($run->manifest_sha256, $digest),
            'checkpoint_match' => $checkpointCount === (int) ceil($run->target_count / $run->chunk_size),
        ];
        $run->update([
            'state' => $qualified ? 'qualified' : 'failed',
            'completed_count' => $items->whereIn('state', ['processed', 'recovered'])->count(),
            'checkpoint_count' => $checkpointCount,
            'isolated_failures' => $items->where('attempt_count', '>', 1)->count(),
            'reconciliation_sha256' => $digest,
            'qualification_profile' => $profile,
            'completed_at' => now(),
        ]);

        return $run->refresh();
    }

    private function refreshProgress(MigrationQualificationRun $run, string $state): MigrationQualificationRun
    {
        $items = DB::table('migration_qualification_items')->where('migration_qualification_run_id', $run->id);
        $run->update([
            'state' => $state,
            'completed_count' => (clone $items)->whereIn('state', ['processed', 'failed', 'recovered'])->count(),
            'checkpoint_count' => DB::table('migration_qualification_checkpoints')->where('migration_qualification_run_id', $run->id)->count(),
            'isolated_failures' => (clone $items)->whereIn('state', ['failed', 'recovered'])->count(),
            'last_checkpoint_at' => now(),
        ]);

        return $run->refresh();
    }

    /** @return list<int> */
    private function failurePositions(int $targetCount): array
    {
        return array_values(array_unique(array_filter([
            min(3711, $targetCount), min(12777, $targetCount), min(24602, $targetCount),
        ], fn (int $position): bool => $position > 0)));
    }

    private function manifestDigest(int $targetCount): string
    {
        return $this->digestFingerprints(array_map(fn (int $position): string => $this->fingerprint($position), range(1, $targetCount)));
    }

    /** @param array<int, string> $fingerprints */
    private function digestFingerprints(array $fingerprints): string
    {
        $context = hash_init('sha256');
        foreach ($fingerprints as $fingerprint) {
            hash_update($context, $fingerprint);
        }

        return hash_final($context);
    }

    private function fingerprint(int $position): string
    {
        return hash('sha256', sprintf('family-archive-qualification:v1:%08d', $position));
    }
}
