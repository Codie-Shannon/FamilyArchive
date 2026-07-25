<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('archive_locations', function (Blueprint $table): void {
            $table->id();
            $table->string('location_id')->unique();
            $table->string('label');
            $table->string('country_code', 2)->nullable();
            $table->string('region')->nullable();
            $table->string('locality')->nullable();
            $table->enum('precision', ['country', 'region', 'locality', 'exact', 'private'])->default('locality');
            $table->boolean('is_sensitive')->default(false);
            $table->timestamps();
        });

        Schema::create('archive_events', function (Blueprint $table): void {
            $table->id();
            $table->string('event_id')->unique();
            $table->string('name');
            $table->string('type')->default('custom');
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->foreignId('archive_location_id')->nullable()->constrained()->restrictOnDelete();
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('family_branches', function (Blueprint $table): void {
            $table->id();
            $table->string('branch_id')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('archive_people', function (Blueprint $table): void {
            $table->id();
            $table->string('person_id')->unique();
            $table->string('display_name');
            $table->json('alternate_names')->nullable();
            $table->unsignedSmallInteger('birth_year')->nullable();
            $table->unsignedSmallInteger('death_year')->nullable();
            $table->enum('life_state', ['living', 'deceased', 'unknown'])->default('unknown');
            $table->enum('identity_state', ['confirmed', 'unknown', 'merged'])->default('confirmed');
            $table->boolean('is_private')->default(true);
            $table->foreignId('family_branch_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('merged_into_id')->nullable()->constrained('archive_people')->restrictOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('family_relationships', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('subject_person_id')->constrained('archive_people')->restrictOnDelete();
            $table->foreignId('related_person_id')->constrained('archive_people')->restrictOnDelete();
            $table->enum('relationship', ['parent', 'child', 'spouse', 'partner', 'sibling', 'custom']);
            $table->string('custom_label')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['subject_person_id', 'related_person_id', 'relationship'], 'family_relationship_unique');
        });

        Schema::create('archive_event_media', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('archive_event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('media_item_id')->constrained()->restrictOnDelete();
            $table->enum('confidence', ['confirmed', 'high', 'medium', 'low', 'unknown'])->default('unknown');
            $table->string('source_note');
            $table->timestamps();
            $table->unique(['archive_event_id', 'media_item_id']);
        });

        Schema::create('archive_person_media', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('archive_person_id')->constrained()->restrictOnDelete();
            $table->foreignId('media_item_id')->constrained()->restrictOnDelete();
            $table->string('context')->nullable();
            $table->enum('confidence', ['confirmed', 'high', 'medium', 'low', 'unknown'])->default('unknown');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['archive_person_id', 'media_item_id']);
        });

        Schema::create('saved_archive_views', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->json('filters');
            $table->boolean('is_favourite')->default(false);
            $table->timestamps();
        });

        Schema::create('curated_collections', function (Blueprint $table): void {
            $table->id();
            $table->string('collection_id')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->foreignId('curated_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('curated_collections');
        Schema::dropIfExists('saved_archive_views');
        Schema::dropIfExists('archive_person_media');
        Schema::dropIfExists('archive_event_media');
        Schema::dropIfExists('family_relationships');
        Schema::dropIfExists('archive_people');
        Schema::dropIfExists('family_branches');
        Schema::dropIfExists('archive_events');
        Schema::dropIfExists('archive_locations');
    }
};
