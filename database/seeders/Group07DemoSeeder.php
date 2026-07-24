<?php

namespace Database\Seeders;

use App\Domain\Duplicates\Actions\ResolveDuplicateCandidate;
use App\Domain\Duplicates\Enums\DuplicateReviewDecision;
use App\Domain\Duplicates\Models\DuplicateCandidate;
use App\Domain\Duplicates\Services\DetectExactDuplicateCandidates;
use App\Domain\Intake\Models\IncomingUpload;
use App\Models\User;
use Illuminate\Database\Seeder;

final class Group07DemoSeeder extends Seeder
{
    public function run(): void
    {
        $owner = User::query()->where('role', 'owner')->firstOrFail();
        $detector = app(DetectExactDuplicateCandidates::class);
        $resolver = app(ResolveDuplicateCandidate::class);
        $decisions = [
            ['confirmed_duplicate', 'Confirmed exact duplicate; source retained.'],
            ['alternate_source', 'Alternate family-held source; no attachment performed.'],
            ['related_but_distinct', 'Same event but a distinct exposure.'],
            ['not_duplicate', 'Hash fixture collision demo classified as not duplicate.'],
        ];
        foreach ($decisions as $i => [$decision, $reason]) {
            $hash = hash('sha256', 'group-07-demo-'.$i);
            $source = IncomingUpload::factory()->create(['upload_id' => sprintf('UP-G07-SOURCE-%03d', $i + 1), 'sha256' => $hash]);
            IncomingUpload::factory()->create(['upload_id' => sprintf('UP-G07-TARGET-%03d', $i + 1), 'sha256' => $hash]);
            $result = $detector->detect($source);
            $candidate = DuplicateCandidate::query()->findOrFail($result->candidateIds[0]);
            $resolver->handle($candidate, DuplicateReviewDecision::from($decision), $reason, $owner, ['route' => 'demo', 'method' => 'SEED']);
        }
        $hash = hash('sha256', 'group-07-demo-pending');
        $source = IncomingUpload::factory()->create(['upload_id' => 'UP-G07-PENDING-001', 'sha256' => $hash]);
        IncomingUpload::factory()->create(['upload_id' => 'UP-G07-PENDING-TARGET', 'sha256' => $hash]);
        $detector->detect($source);
    }
}
