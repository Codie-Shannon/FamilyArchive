<?php

namespace App\Domain\Intake\ValueObjects;

use App\Domain\Archive\Models\ArchivePromotion;
use App\Domain\Processing\Models\ProcessingJob;
use App\Domain\Processing\Models\RestorationCandidate;

final readonly class IncomingPhotoAutomationResult
{
    /** @param list<int> $duplicateCandidateIds */
    public function __construct(
        public string $state,
        public array $duplicateCandidateIds = [],
        public ?ArchivePromotion $promotion = null,
        public ?ProcessingJob $job = null,
        public ?RestorationCandidate $candidate = null,
    ) {}
}
