<?php

namespace App\Console\Commands;

use App\Domain\CloudImport\Services\ArchiveMigrationQualification;
use App\Models\User;
use Illuminate\Console\Command;
use RuntimeException;

final class QualifyArchiveMigrationCommand extends Command
{
    protected $signature = 'archive:qualify-migration
        {--files=30000 : Synthetic manifest entries to qualify}
        {--chunk=500 : Entries per durable checkpoint}
        {--interrupt-after=12000 : Inject an interruption after this many entries}
        {--operator= : Approved operator email}';

    protected $description = 'Exercise checkpoint, interruption, replay and reconciliation safety with a synthetic migration manifest';

    public function handle(ArchiveMigrationQualification $qualification): int
    {
        $operator = $this->operator();
        $result = $qualification->qualify(
            $operator,
            max(0, (int) $this->option('files')),
            max(0, (int) $this->option('chunk')),
            max(1, (int) $this->option('interrupt-after')),
        );

        $this->components->info('Synthetic qualification completed. No source media was read or retained.');
        $this->components->twoColumnDetail('Qualification', $result['qualification_id']);
        $this->components->twoColumnDetail('Manifest entries', number_format($result['target_count']));
        $this->components->twoColumnDetail('Durable checkpoints', number_format($result['checkpoint_count']));
        $this->components->twoColumnDetail('Result', strtoupper($result['state']));
        $this->newLine();
        $this->components->warn('The real private family batch remains a separate operator-controlled migration.');

        return $result['state'] === 'qualified' ? self::SUCCESS : self::FAILURE;
    }

    private function operator(): User
    {
        $email = trim((string) $this->option('operator'));
        $query = User::query()->where('account_state', 'approved')->whereIn('role', ['owner', 'admin', 'trusted_contributor']);
        $operator = $email === ''
            ? $query->orderByRaw("CASE WHEN role = 'owner' THEN 0 ELSE 1 END")->first()
            : $query->where('email', $email)->first();
        if ($operator === null) {
            throw new RuntimeException('No approved migration operator was found.');
        }

        return $operator;
    }
}
