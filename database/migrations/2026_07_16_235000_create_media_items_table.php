<?php

use App\Domain\Media\Enums\DateConfidence;
use App\Domain\Media\Enums\MediaReviewStatus;
use App\Domain\Media\Enums\MediaVisibility;
use App\Domain\Media\Enums\SensitivityStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media_items', function (Blueprint $table): void {
            $table->id();
            $table->string('archive_id', 32)->unique();
            $table->string('media_type', 32)->index();
            $table->string('title')->nullable();
            $table->text('description')->nullable();
            $table->longText('story')->nullable();
            $table->date('canonical_date')->nullable();
            $table->unsignedSmallInteger('estimated_decade')->nullable();
            $table->string('date_confidence', 32)->default(DateConfidence::Unknown->value);
            $table->string('visibility', 64)->default(MediaVisibility::PrivateArchive->value)->index();
            $table->string('review_status', 32)->default(MediaReviewStatus::PendingReview->value)->index();
            $table->string('sensitivity_status', 32)->default(SensitivityStatus::NotFlagged->value)->index();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        throw new \LogicException('The media_items migration is forward-only.');
    }
};
