<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cloud_import_connections', function (Blueprint $table): void {
            $table->id();
            $table->uuid('connection_id')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('provider', ['google_photos', 'apple_photos', 'manual_export']);
            $table->enum('state', ['unconfigured', 'authorizing', 'ready', 'expired', 'revoked', 'unvalidated'])->default('unconfigured');
            $table->json('safe_capabilities');
            $table->text('encrypted_credentials')->nullable();
            $table->timestamps();
        });

        Schema::create('cloud_import_sessions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('session_id')->unique();
            $table->foreignId('cloud_import_connection_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->enum('provider', ['google_photos', 'apple_photos', 'manual_export']);
            $table->enum('state', ['selecting', 'preflight', 'importing', 'paused', 'complete', 'failed'])->default('selecting')->index();
            $table->unsignedInteger('selected_count')->default(0);
            $table->unsignedInteger('imported_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->json('source_manifest')->nullable();
            $table->timestamps();
        });

        Schema::create('cloud_import_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('cloud_import_session_id')->constrained()->cascadeOnDelete();
            $table->string('external_id');
            $table->enum('media_type', ['photo', 'video', 'audio', 'document']);
            $table->string('original_name');
            $table->string('source_checksum', 64)->nullable();
            $table->unsignedBigInteger('source_bytes')->nullable();
            $table->json('source_metadata')->nullable();
            $table->enum('state', ['selected', 'validated', 'retained', 'duplicate_candidate', 'failed'])->default('selected');
            $table->foreignId('incoming_upload_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
            $table->unique(['cloud_import_session_id', 'external_id']);
        });

        Schema::create('media_playback_profiles', function (Blueprint $table): void {
            $table->id();
            $table->enum('media_type', ['video', 'audio', 'document']);
            $table->string('name');
            $table->unsignedInteger('version');
            $table->json('recipe');
            $table->boolean('is_active')->default(false);
            $table->timestamps();
            $table->unique(['media_type', 'name', 'version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media_playback_profiles');
        Schema::dropIfExists('cloud_import_items');
        Schema::dropIfExists('cloud_import_sessions');
        Schema::dropIfExists('cloud_import_connections');
    }
};
