<?php

namespace App\Console\Commands;

use App\Domain\Duplicates\Services\DetectExactDuplicateCandidates;
use App\Domain\Intake\Models\IncomingUpload;
use Illuminate\Console\Command;

final class DetectExactDuplicateCandidatesCommand extends Command
{
    protected $signature = 'archive:detect-exact-duplicates {upload : IncomingUpload database ID or upload_id}';
    protected $description = 'Create pending manual-review candidates from stored exact SHA-256 facts without reading media bytes.';

    public function handle(DetectExactDuplicateCandidates $detector): int
    {
        $identifier = (string) $this->argument('upload');
        $upload = IncomingUpload::query()
            ->when(ctype_digit($identifier), fn ($query) => $query->whereKey((int) $identifier), fn ($query) => $query->where('upload_id', $identifier))
            ->firstOrFail();

        $result = $detector->detect($upload);
        $this->info("candidate_count={$result->candidateCount}");
        $this->line('candidate_ids='.implode(',', $result->candidateIds));
        $this->line('No media bytes were read or changed. Manual review remains required.');

        return self::SUCCESS;
    }
}
