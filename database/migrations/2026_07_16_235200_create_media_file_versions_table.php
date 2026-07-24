<?php

use App\Domain\Media\Enums\GenerationStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media_file_versions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('media_item_id')->constrained('media_items')->restrictOnDelete();
            $table->foreignId('parent_version_id')->nullable()->constrained('media_file_versions')->restrictOnDelete();
            $table->string('version_type', 32)->index();
            $table->string('storage_disk', 64);
            $table->string('storage_path', 768)->unique();
            $table->string('mime_type', 127);
            $table->string('extension', 16)->nullable();
            $table->unsignedBigInteger('file_size_bytes');
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->unsignedBigInteger('duration_ms')->nullable();
            $table->char('sha256', 64)->index();
            $table->string('perceptual_hash', 128)->nullable()->index();
            $table->string('generation_status', 32)->default(GenerationStatus::Pending->value)->index();
            $table->json('generation_recipe')->nullable();
            $table->boolean('is_preferred')->default(false)->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        throw new LogicException('The media_file_versions migration is forward-only.');
    }
};
