<?php

namespace App\Console\Commands;

use App\Domain\Storage\Exceptions\WasabiProviderException;
use App\Domain\Storage\Services\WasabiArchiveMigrator;
use Illuminate\Console\Command;

final class MigrateArchiveToWasabiCommand extends Command
{
    protected $signature = 'archive:wasabi-migrate
        {--execute : Copy and verify objects; without this flag the command is read-only}
        {--limit=0 : Maximum number of objects to inspect, where zero means all}';

    protected $description = 'Dry-run or execute a copy-first, verified, no-delete migration to Wasabi';

    public function handle(WasabiArchiveMigrator $migrator): int
    {
        $execute = (bool) $this->option('execute');
        $limit = filter_var($this->option('limit'), FILTER_VALIDATE_INT);
        if ($limit === false || $limit < 0) {
            $this->components->error('The limit must be a non-negative integer.');

            return self::INVALID;
        }

        $this->components->info($execute
            ? 'Executing copy-first migration. Local source objects will not be deleted.'
            : 'Planning only. No provider request or storage mutation will occur.');

        try {
            $summary = $migrator->migrate($execute, $limit);
        } catch (WasabiProviderException $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->table(
            ['Planned', 'Copied', 'Verified existing', 'Bytes'],
            [[
                $summary['planned'],
                $summary['copied'],
                $summary['verified_existing'],
                $summary['bytes'],
            ]],
        );
        $this->line('Local source deletion is intentionally unavailable.');

        return self::SUCCESS;
    }
}
