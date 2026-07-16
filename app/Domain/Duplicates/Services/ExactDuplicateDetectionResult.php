<?php

namespace App\Domain\Duplicates\Services;

final readonly class ExactDuplicateDetectionResult
{
    /** @param list<int> $candidateIds */
    public function __construct(public int $candidateCount, public array $candidateIds) {}
}
