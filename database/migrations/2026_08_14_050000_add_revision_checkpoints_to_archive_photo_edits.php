<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('archive_photo_edit_drafts', 'client_revision')) {
            Schema::table('archive_photo_edit_drafts', function (Blueprint $table): void {
                $table->unsignedBigInteger('client_revision')->default(0)->after('from_source_scan');
            });
        }

        if (! Schema::hasColumn('archive_photo_edit_batch_items', 'draft_client_revision')) {
            Schema::table('archive_photo_edit_batch_items', function (Blueprint $table): void {
                $table->unsignedBigInteger('draft_client_revision')->default(0)->after('draft_fingerprint');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('archive_photo_edit_batch_items', 'draft_client_revision')) {
            Schema::table('archive_photo_edit_batch_items', function (Blueprint $table): void {
                $table->dropColumn('draft_client_revision');
            });
        }

        if (Schema::hasColumn('archive_photo_edit_drafts', 'client_revision')) {
            Schema::table('archive_photo_edit_drafts', function (Blueprint $table): void {
                $table->dropColumn('client_revision');
            });
        }
    }
};
