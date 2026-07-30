<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('public_showcase_entries', function (Blueprint $table): void {
            $table->id();
            $table->uuid('entry_id')->unique();
            $table->foreignId('media_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('approved_by')->constrained('users')->restrictOnDelete();
            $table->string('public_title');
            $table->text('public_summary')->nullable();
            $table->enum('state', ['draft', 'review', 'published', 'withdrawn'])->default('draft')->index();
            $table->timestamp('published_at')->nullable();
            $table->boolean('allow_social_cards')->default(false);
            $table->timestamps();
        });

        Schema::create('public_map_points', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('public_showcase_entry_id')->constrained()->cascadeOnDelete();
            $table->decimal('latitude', 9, 6);
            $table->decimal('longitude', 9, 6);
            $table->enum('precision', ['exact', 'neighbourhood', 'town', 'region'])->default('town');
            $table->string('public_place_name')->nullable();
            $table->boolean('privacy_reviewed')->default(false);
            $table->timestamps();
        });

        Schema::create('social_publication_receipts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('public_showcase_entry_id')->constrained()->cascadeOnDelete();
            $table->string('channel');
            $table->string('external_reference')->nullable();
            $table->enum('state', ['queued', 'published', 'failed', 'withdrawn'])->default('queued');
            $table->json('safe_response')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('social_publication_receipts');
        Schema::dropIfExists('public_map_points');
        Schema::dropIfExists('public_showcase_entries');
    }
};
