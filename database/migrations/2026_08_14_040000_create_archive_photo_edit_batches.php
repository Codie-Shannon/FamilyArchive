<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('archive_photo_edit_batches')) {
            Schema::create('archive_photo_edit_batches', function (Blueprint $table): void {
                $table->id();
                $table->uuid('batch_id')->unique();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->foreignId('active_user_id')->nullable()->unique()->constrained('users')->nullOnDelete();
                $table->string('state', 32)->default('queued')->index();
                $table->unsignedInteger('total_count');
                $table->unsignedInteger('completed_count')->default(0);
                $table->unsignedInteger('failed_count')->default(0);
                $table->timestamp('started_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->timestamps();
                $table->index(['user_id', 'created_at']);
            });
        }

        if (! Schema::hasTable('archive_photo_edit_batch_items')) {
            Schema::create('archive_photo_edit_batch_items', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('archive_photo_edit_batch_id')->constrained()->cascadeOnDelete();
                $table->foreignId('media_item_id')->constrained()->restrictOnDelete();
                $table->foreignId('source_version_id')->constrained('media_file_versions')->restrictOnDelete();
                $table->unsignedBigInteger('draft_id');
                $table->string('draft_fingerprint', 64);
                $table->json('settings');
                $table->unsignedInteger('expected_metadata_revision');
                $table->boolean('from_source_scan')->default(false);
                $table->unsignedInteger('position');
                $table->string('state', 24)->default('queued')->index();
                $table->unsignedTinyInteger('attempts')->default(0);
                $table->foreignId('published_version_id')->nullable()->constrained('media_file_versions')->restrictOnDelete();
                $table->string('failure_code', 80)->nullable();
                $table->string('failure_message', 500)->nullable();
                $table->timestamp('started_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->timestamps();
                $table->unique(['archive_photo_edit_batch_id', 'media_item_id'], 'archive_photo_edit_batch_media_unique');
                $table->index(['archive_photo_edit_batch_id', 'position'], 'archive_photo_edit_batch_position_index');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('archive_photo_edit_batch_items');
        Schema::dropIfExists('archive_photo_edit_batches');
    }
};
