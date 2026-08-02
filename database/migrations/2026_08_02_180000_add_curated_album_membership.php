<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('curated_collections', function (Blueprint $table): void {
            $table->boolean('is_published')->default(true)->after('description');
        });

        Schema::create('curated_collection_media', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('curated_collection_id')->constrained('curated_collections')->cascadeOnDelete();
            $table->foreignId('media_item_id')->constrained()->restrictOnDelete();
            $table->foreignId('added_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
            $table->unique(['curated_collection_id', 'media_item_id'], 'curated_collection_media_unique');
            $table->index(['curated_collection_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('curated_collection_media');

        Schema::table('curated_collections', function (Blueprint $table): void {
            $table->dropColumn('is_published');
        });
    }
};
