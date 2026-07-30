<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media_perceptual_fingerprints', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('media_file_version_id')->constrained()->restrictOnDelete();
            $table->enum('algorithm', ['dhash64', 'phash64']);
            $table->string('fingerprint', 16);
            $table->string('generator_version');
            $table->timestamps();
            $table->unique(
                ['media_file_version_id', 'algorithm', 'generator_version'],
                'media_fingerprint_version_unique',
            );
            $table->index(['algorithm', 'fingerprint']);
        });

        Schema::create('visual_similarity_candidates', function (Blueprint $table): void {
            $table->id();
            $table->uuid('candidate_id')->unique();
            $table->foreignId('source_version_id')->constrained('media_file_versions')->restrictOnDelete();
            $table->foreignId('target_version_id')->constrained('media_file_versions')->restrictOnDelete();
            $table->enum('method', ['perceptual', 'crop_similarity', 'rescan_similarity']);
            $table->unsignedTinyInteger('distance');
            $table->decimal('confidence', 5, 4);
            $table->enum('review_state', ['pending', 'related', 'duplicate', 'not_related'])
                ->default('pending')
                ->index();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('review_reason')->nullable();
            $table->timestamps();
            $table->unique(
                ['source_version_id', 'target_version_id', 'method'],
                'visual_candidate_pair_unique',
            );
        });

        Schema::create('alternate_media_sources', function (Blueprint $table): void {
            $table->id();
            $table->uuid('alternate_id')->unique();
            $table->foreignId('media_item_id')->constrained()->restrictOnDelete();
            $table->foreignId('media_file_version_id')->constrained()->restrictOnDelete();
            $table->foreignId('source_collection_id')->nullable()->constrained()->restrictOnDelete();
            $table->boolean('is_preferred_source')->default(false);
            $table->text('provenance_note');
            $table->foreignId('reviewed_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->unique('media_file_version_id');
        });

        Schema::create('metadata_merge_proposals', function (Blueprint $table): void {
            $table->id();
            $table->uuid('proposal_id')->unique();
            $table->foreignId('target_media_item_id')->constrained('media_items')->restrictOnDelete();
            $table->foreignId('source_media_item_id')->constrained('media_items')->restrictOnDelete();
            $table->json('field_decisions');
            $table->json('conflicts');
            $table->enum('state', ['draft', 'pending_review', 'accepted', 'rejected'])
                ->default('draft')
                ->index();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('metadata_merge_proposals');
        Schema::dropIfExists('alternate_media_sources');
        Schema::dropIfExists('visual_similarity_candidates');
        Schema::dropIfExists('media_perceptual_fingerprints');
    }
};
