<?php

namespace App\Console\Commands;

use App\Domain\CloudImport\Services\TrustedBatchReview;
use App\Models\User;
use Illuminate\Console\Command;

final class ReclassifyTrustedBatchReviewCommand extends Command
{
    protected $signature = 'archive:reclassify-batch-review {session} {owner} {--limit=50 : Maximum pending prepared rows to reassess}';

    protected $description = 'Reassess pending batch exceptions without approving, rejecting, or regenerating photos';

    public function handle(TrustedBatchReview $reviews): int
    {
        $owner = User::query()->where('email', $this->argument('owner'))->first();
        if (! $owner instanceof User || ! $owner->canManageTrustedIntake()) {
            $this->error('The supplied account does not have trusted intake access.');

            return self::FAILURE;
        }

        $result = $reviews->reclassifyPending(
            (string) $this->argument('session'),
            $owner,
            max(1, min(50, (int) $this->option('limit'))),
        );

        $this->info("Reclassified {$result['reclassified']} pending rows; {$result['eligible']} are now routine and {$result['attention']} remain in the batch attention queue.");
        $this->line("Failures: {$result['failed']}");
        $this->comment('No review decisions were made and no originals or suggestions were replaced.');

        return $result['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
