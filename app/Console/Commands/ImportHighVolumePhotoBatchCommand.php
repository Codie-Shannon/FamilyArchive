<?php

namespace App\Console\Commands;

use App\Domain\CloudImport\Services\HighVolumePhotoBatch;
use App\Models\User;
use Illuminate\Console\Command;

final class ImportHighVolumePhotoBatchCommand extends Command
{
    protected $signature = 'archive:batch-import {directory} {owner} {--batch=} {--limit=} {--chunk=500} {--inventory-only}';

    protected $description = 'Inventory or resume a large local photo batch without bypassing quarantine or review';

    public function handle(HighVolumePhotoBatch $batches): int
    {
        $owner = User::query()->where('email', $this->argument('owner'))->first();
        if (! $owner instanceof User || ! $owner->canManageTrustedIntake()) {
            $this->error('The supplied account does not have trusted intake access.');

            return self::FAILURE;
        }
        $batchId = $this->option('batch');
        if (! is_string($batchId) || $batchId === '') {
            $planned = $batches->plan($owner, (string) $this->argument('directory'), (int) $this->option('chunk'));
            $batchId = $planned['session_id'];
            $this->info("Inventory planned: {$planned['selected_count']} files, {$planned['total_bytes']} bytes.");
            $this->line("Resume token: {$batchId}");
        }
        if ($this->option('inventory-only')) {
            $this->comment('Inventory only. No source bytes were retained.');

            return self::SUCCESS;
        }
        $limit = $this->option('limit');
        $result = $batches->process($batchId, (string) $this->argument('directory'), is_numeric($limit) ? (int) $limit : null);
        $this->info("Checkpoint: {$result['processed_count']} processed, {$result['retained_count']} retained, {$result['failed_count']} failed, {$result['remaining_count']} remaining.");
        $this->line("Batch state: {$result['state']}");

        return $result['state'] === 'failed' ? self::FAILURE : self::SUCCESS;
    }
}
