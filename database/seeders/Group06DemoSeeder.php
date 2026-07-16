<?php

namespace Database\Seeders;

use App\Domain\Duplicates\Services\DetectExactDuplicateCandidates;
use App\Domain\Intake\Models\IncomingUpload;
use App\Domain\Media\Enums\MediaFileVersionType;
use App\Domain\Media\Models\MediaFileVersion;
use Illuminate\Database\Seeder;

final class Group06DemoSeeder extends Seeder
{
    public function run(): void
    {
        $uploadHash = hash('sha256', 'group-06-fictional-retained-upload');
        $originalHash = hash('sha256', 'group-06-fictional-archived-original');

        $source = IncomingUpload::factory()->create([
            'upload_id' => 'UP-G06-SOURCE-001', 'original_filename' => 'fictional-source-001.jpg',
            'incoming_path' => 'incoming/group-06/fictional-source-001.jpg', 'sha256' => $uploadHash,
            'submitted_at' => now()->subMinutes(8),
        ]);
        IncomingUpload::factory()->create([
            'upload_id' => 'UP-G06-TARGET-001', 'original_filename' => 'fictional-retained-target.jpg',
            'incoming_path' => 'incoming/group-06/fictional-retained-target.jpg', 'sha256' => $uploadHash,
            'submitted_at' => now()->subMinutes(20),
        ]);
        $originalSource = IncomingUpload::factory()->create([
            'upload_id' => 'UP-G06-SOURCE-002', 'original_filename' => 'fictional-original-match.jpg',
            'incoming_path' => 'incoming/group-06/fictional-original-match.jpg', 'sha256' => $originalHash,
            'submitted_at' => now()->subMinutes(5),
        ]);
        MediaFileVersion::factory()->create([
            'version_type' => MediaFileVersionType::Original,
            'storage_disk' => 'archive_originals',
            'storage_path' => 'fictional/MI-DEMO-G06/original/fictional-archived-original.jpg',
            'sha256' => $originalHash,
        ]);
        $noMatch = IncomingUpload::factory()->create([
            'upload_id' => 'UP-G06-NOMATCH-001', 'original_filename' => 'fictional-no-match.jpg',
            'incoming_path' => 'incoming/group-06/fictional-no-match.jpg',
            'sha256' => hash('sha256', 'group-06-fictional-no-match'),
            'submitted_at' => now()->subMinutes(2),
        ]);

        $detector = app(DetectExactDuplicateCandidates::class);
        $detector->detect($source);
        $detector->detect($originalSource);
        $detector->detect($noMatch);
    }
}
