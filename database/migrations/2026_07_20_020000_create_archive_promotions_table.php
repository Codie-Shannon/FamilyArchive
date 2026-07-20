<?php

use App\Domain\Media\Enums\MediaType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('incoming_uploads', function (Blueprint $table): void {
            $table->string('media_type', 32)
                ->default(MediaType::Photo->value)
                ->after('media_item_id')
                ->index();
        });

        Schema::create('archive_promotions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('incoming_upload_id')->unique()->constrained('incoming_uploads')->restrictOnDelete();
            $table->foreignId('media_item_id')->unique()->constrained('media_items')->restrictOnDelete();
            $table->foreignId('original_media_file_version_id')->unique()->constrained('media_file_versions')->restrictOnDelete();
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->string('source_disk', 64);
            $table->string('source_path', 1024);
            $table->string('target_disk', 64);
            $table->string('target_path', 768)->unique();
            $table->unsignedBigInteger('source_bytes');
            $table->unsignedBigInteger('target_bytes');
            $table->char('source_sha256', 64);
            $table->char('target_sha256', 64);
            $table->timestamp('promoted_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        throw new LogicException('The Group 08 archive promotion migration is forward-only.');
    }
};
