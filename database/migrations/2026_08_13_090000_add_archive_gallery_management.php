<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_archive_preferences', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('photo_gallery_rows')->default(4);
            $table->timestamps();
        });

        Schema::create('archive_selection_drafts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('context', 120);
            $table->timestamps();
            $table->unique(['user_id', 'context']);
        });

        Schema::create('archive_selection_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('archive_selection_draft_id')->constrained()->cascadeOnDelete();
            $table->foreignId('media_item_id')->constrained()->cascadeOnDelete();
            $table->timestamp('selected_at');
            $table->unsignedInteger('source_page')->default(1);
            $table->unique(['archive_selection_draft_id', 'media_item_id'], 'archive_selection_item_unique');
        });

        Schema::table('media_items', function (Blueprint $table): void {
            $table->timestamp('hidden_at')->nullable()->after('approved_at')->index();
            $table->foreignId('hidden_by')->nullable()->after('hidden_at')->constrained('users')->nullOnDelete();
            $table->string('hidden_previous_visibility', 64)->nullable()->after('hidden_by');
            $table->string('hide_reason_category', 64)->nullable()->after('hidden_previous_visibility');
            $table->text('hide_reason_note')->nullable()->after('hide_reason_category');
        });

        Schema::create('photo_visibility_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('media_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('actor_user_id')->constrained('users')->restrictOnDelete();
            $table->string('action', 16);
            $table->string('previous_visibility', 64);
            $table->string('new_visibility', 64);
            $table->string('reason_category', 64);
            $table->text('reason_note')->nullable();
            $table->boolean('batch_action')->default(false);
            $table->unsignedInteger('from_metadata_revision');
            $table->unsignedInteger('to_metadata_revision');
            $table->timestamp('occurred_at');
            $table->index(['media_item_id', 'occurred_at']);
        });

        Schema::create('archive_photo_edit_drafts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('media_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('source_version_id')->constrained('media_file_versions')->restrictOnDelete();
            $table->json('settings');
            $table->unsignedInteger('expected_metadata_revision');
            $table->boolean('from_source_scan')->default(false);
            $table->timestamps();
            $table->unique(['user_id', 'media_item_id']);
        });
    }

    public function down(): void
    {
        throw new LogicException('Archive gallery management is preservation-audited and forward-only.');
    }
};
