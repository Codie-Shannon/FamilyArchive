<?php

use App\Domain\Intake\Enums\DuplicateStatus;
use App\Domain\Intake\Enums\IncomingProcessingStatus;
use App\Domain\Intake\Enums\IncomingReviewStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('incoming_uploads', function (Blueprint $table): void {
            $table->id();
            $table->string('upload_id', 32)->unique();
            $table->foreignId('uploader_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('media_item_id')->nullable()->constrained('media_items')->restrictOnDelete();
            $table->string('original_filename');
            $table->string('incoming_path', 1024)->nullable();
            $table->string('mime_type', 127);
            $table->string('extension', 16)->nullable();
            $table->unsignedBigInteger('file_size_bytes');
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->unsignedBigInteger('duration_ms')->nullable();
            $table->char('sha256', 64)->index();
            $table->string('perceptual_hash', 128)->nullable()->index();
            $table->string('processing_status', 32)->default(IncomingProcessingStatus::Pending->value)->index();
            $table->string('review_status', 32)->default(IncomingReviewStatus::PendingReview->value)->index();
            $table->string('duplicate_status', 32)->default(DuplicateStatus::NotChecked->value)->index();
            $table->boolean('source_file_retained')->default(true);
            $table->timestamp('source_file_removed_at')->nullable();
            $table->timestamp('submitted_at')->useCurrent();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        throw new \LogicException('The incoming_uploads migration is forward-only.');
    }
};
