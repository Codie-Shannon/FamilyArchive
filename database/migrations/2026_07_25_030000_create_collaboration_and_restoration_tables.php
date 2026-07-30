<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('identity_suggestions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('media_item_id')->constrained()->restrictOnDelete();
            $table->foreignId('archive_person_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('suggested_by')->constrained('users')->restrictOnDelete();
            $table->text('reason');
            $table->enum('state', ['pending', 'accepted', 'rejected'])->default('pending');
            $table->timestamps();
        });

        Schema::create('archive_notifications', function (Blueprint $table): void {
            $table->id();
            $table->uuid('notification_id')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->string('title');
            $table->json('context')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });

        Schema::create('processing_recipes', function (Blueprint $table): void {
            $table->id();
            $table->string('recipe_id')->unique();
            $table->string('name');
            $table->unsignedInteger('version');
            $table->json('operations');
            $table->boolean('is_batch_profile')->default(false);
            $table->timestamps();
            $table->unique(['name', 'version']);
        });

        Schema::create('processing_jobs', function (Blueprint $table): void {
            $table->id();
            $table->uuid('job_id')->unique();
            $table->foreignId('media_item_id')->constrained()->restrictOnDelete();
            $table->foreignId('processing_recipe_id')->constrained()->restrictOnDelete();
            $table->enum('state', ['queued', 'running', 'candidate_ready', 'approved', 'rejected', 'failed'])->default('queued')->index();
            $table->unsignedInteger('attempts')->default(0);
            $table->text('failure_reason')->nullable();
            $table->timestamps();
        });

        Schema::create('restoration_candidates', function (Blueprint $table): void {
            $table->id();
            $table->uuid('candidate_id')->unique();
            $table->foreignId('processing_job_id')->constrained()->cascadeOnDelete();
            $table->foreignId('source_version_id')->constrained('media_file_versions')->restrictOnDelete();
            $table->foreignId('candidate_version_id')->nullable()->constrained('media_file_versions')->restrictOnDelete();
            $table->json('quality_checks');
            $table->enum('review_state', ['pending', 'approved', 'rejected'])->default('pending');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('storage_provider_statuses', function (Blueprint $table): void {
            $table->id();
            $table->string('provider')->unique();
            $table->enum('state', ['unconfigured', 'healthy', 'degraded', 'unavailable'])->default('unconfigured');
            $table->json('capabilities');
            $table->timestamp('checked_at')->nullable();
            $table->text('safe_message')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach ([
            'storage_provider_statuses',
            'restoration_candidates',
            'processing_jobs',
            'processing_recipes',
            'archive_notifications',
            'identity_suggestions',
        ] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
