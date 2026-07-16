<?php

namespace Database\Seeders;

use App\Domain\Intake\Enums\DuplicateStatus;
use App\Domain\Intake\Enums\IncomingProcessingStatus;
use App\Domain\Intake\Enums\IncomingReviewStatus;
use App\Domain\Intake\Models\IncomingUpload;
use App\Models\User;
use Illuminate\Database\Seeder;

final class Group04DemoSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('local')) {
            throw new \RuntimeException('Group04DemoSeeder is local-only.');
        }
        if (IncomingUpload::query()->where('upload_id', 'not like', 'UP_DEMO_%')->exists()) {
            throw new \RuntimeException('Refusing to mix Group 04 demo rows with non-demo records.');
        }
        $owner = User::query()->where('role', 'owner')->firstOrFail();
        foreach ([['UP_DEMO_01HZX000000000000000000001', 'fictional-grid.jpg', 'image/jpeg', 'jpg', 640, 480], ['UP_DEMO_01HZX000000000000000000002', 'fictional-shapes.png', 'image/png', 'png', 800, 600]] as [$id,$name,$mime,$ext,$w,$h]) {
            IncomingUpload::query()->firstOrCreate(['upload_id' => $id], ['uploader_id' => $owner->id, 'original_filename' => $name, 'incoming_path' => 'incoming/'.$id.'/'.$name, 'mime_type' => $mime, 'extension' => $ext, 'file_size_bytes' => 4096, 'width' => $w, 'height' => $h, 'duration_ms' => null, 'sha256' => null, 'perceptual_hash' => null, 'processing_status' => IncomingProcessingStatus::Pending, 'review_status' => IncomingReviewStatus::PendingReview, 'duplicate_status' => DuplicateStatus::NotChecked, 'source_file_retained' => false, 'submitted_at' => now()]);
        }
    }
}
