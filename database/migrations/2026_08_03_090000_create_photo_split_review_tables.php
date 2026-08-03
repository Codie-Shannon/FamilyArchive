<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('photo_split_proposals', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('cloud_import_item_id')->unique();
            $table->foreignId('source_version_id')->constrained('media_file_versions')->restrictOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('state', ['suggested', 'ready', 'dismissed', 'published'])->default('suggested')->index();
            $table->decimal('confidence', 5, 4)->default(0);
            $table->string('detection_method', 64);
            $table->json('analysis');
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->foreign('cloud_import_item_id', 'photo_split_proposal_item_fk')
                ->references('id')->on('cloud_import_items')->cascadeOnDelete();
        });

        Schema::create('photo_split_regions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('photo_split_proposal_id');
            $table->uuid('region_id')->unique();
            $table->unsignedSmallInteger('position');
            $table->unsignedSmallInteger('x_basis_points');
            $table->unsignedSmallInteger('y_basis_points');
            $table->unsignedSmallInteger('width_basis_points');
            $table->unsignedSmallInteger('height_basis_points');
            $table->decimal('confidence', 5, 4)->default(0);
            $table->enum('source', ['detected', 'manual'])->default('detected');
            $table->enum('review_state', ['included', 'excluded'])->default('included');
            $table->foreignId('candidate_version_id')->nullable()->constrained('media_file_versions')->nullOnDelete();
            $table->foreignId('output_media_item_id')->nullable()->constrained('media_items')->nullOnDelete();
            $table->timestamps();

            $table->unique(['photo_split_proposal_id', 'position'], 'photo_split_region_position_unique');
            $table->foreign('photo_split_proposal_id', 'photo_split_region_proposal_fk')
                ->references('id')->on('photo_split_proposals')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('photo_split_regions');
        Schema::dropIfExists('photo_split_proposals');
    }
};
