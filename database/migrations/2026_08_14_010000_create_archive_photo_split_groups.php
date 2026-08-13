<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('archive_photo_split_groups', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('source_media_item_id')->constrained('media_items')->restrictOnDelete();
            $table->foreignId('source_version_id')->constrained('media_file_versions')->restrictOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->string('source_basis', 16);
            $table->timestamp('published_at');
            $table->timestamps();
        });

        Schema::create('archive_photo_split_members', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('archive_photo_split_group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('media_item_id')->unique()->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('position');
            $table->json('bounds');
            $table->timestamps();
            $table->unique(['archive_photo_split_group_id', 'position'], 'archive_photo_split_group_position_unique');
        });
    }

    public function down(): void
    {
        throw new LogicException('Published archive split lineage is preservation-audited and forward-only.');
    }
};
