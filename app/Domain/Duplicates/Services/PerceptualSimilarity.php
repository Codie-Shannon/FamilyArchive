<?php

namespace App\Domain\Duplicates\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class PerceptualSimilarity
{
    public function distance(string $left, string $right): int
    {
        if (! preg_match('/^[0-9a-f]{16}$/i', $left) || ! preg_match('/^[0-9a-f]{16}$/i', $right)) {
            throw ValidationException::withMessages([
                'fingerprint' => 'A 64-bit hexadecimal fingerprint is required.',
            ]);
        }

        $distance = 0;

        for ($index = 0; $index < 16; $index++) {
            $xor = hexdec($left[$index]) ^ hexdec($right[$index]);
            $distance += substr_count(decbin($xor), '1');
        }

        return $distance;
    }

    public function recordCandidate(
        int $sourceVersionId,
        int $targetVersionId,
        string $left,
        string $right,
        string $method = 'perceptual',
    ): string {
        if ($sourceVersionId === $targetVersionId) {
            throw ValidationException::withMessages([
                'target' => 'A version cannot be compared with itself.',
            ]);
        }

        [$sourceVersionId, $targetVersionId] = $sourceVersionId < $targetVersionId
            ? [$sourceVersionId, $targetVersionId]
            : [$targetVersionId, $sourceVersionId];

        $distance = $this->distance($left, $right);
        $candidateId = (string) Str::uuid();

        DB::table('visual_similarity_candidates')->insert([
            'candidate_id' => $candidateId,
            'source_version_id' => $sourceVersionId,
            'target_version_id' => $targetVersionId,
            'method' => $method,
            'distance' => $distance,
            'confidence' => number_format(max(0, 1 - ($distance / 64)), 4, '.', ''),
            'review_state' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $candidateId;
    }
}
