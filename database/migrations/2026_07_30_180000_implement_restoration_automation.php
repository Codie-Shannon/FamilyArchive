<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('processing_recipes', function (Blueprint $table): void {
            $table->foreignId('created_by')->nullable()->after('id')->constrained('users')->nullOnDelete();
            $table->string('automation_source', 32)->default('owner')->after('operations');
            $table->boolean('is_active')->default(true)->after('is_batch_profile')->index();
        });

        Schema::table('processing_jobs', function (Blueprint $table): void {
            $table->foreignId('source_version_id')->nullable()->after('media_item_id')->constrained('media_file_versions')->restrictOnDelete();
            $table->foreignId('requested_by')->nullable()->after('processing_recipe_id')->constrained('users')->nullOnDelete();
            $table->json('automation_preferences')->nullable()->after('requested_by');
            $table->timestamp('started_at')->nullable()->after('failure_reason');
            $table->timestamp('completed_at')->nullable()->after('started_at');
        });

        Schema::table('restoration_candidates', function (Blueprint $table): void {
            $table->json('analysis')->nullable()->after('quality_checks');
            $table->json('operations_applied')->nullable()->after('analysis');
            $table->text('review_note')->nullable()->after('reviewed_by');
            $table->timestamp('reviewed_at')->nullable()->after('review_note');
        });

        Schema::create('processing_job_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('processing_job_id')->constrained()->cascadeOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('event', 64)->index();
            $table->json('safe_context')->nullable();
            $table->timestamp('occurred_at')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        throw new LogicException('The restoration automation migration is forward-only.');
    }
};
