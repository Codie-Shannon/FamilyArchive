<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cloud_import_sessions', function (Blueprint $table): void {
            $table->unsignedBigInteger('total_bytes')->default(0)->after('failed_count');
            $table->unsignedInteger('processed_count')->default(0)->after('total_bytes');
            $table->unsignedInteger('checkpoint_position')->default(0)->after('processed_count');
            $table->unsignedSmallInteger('chunk_size')->default(500)->after('checkpoint_position');
            $table->string('inventory_sha256', 64)->nullable()->after('chunk_size');
            $table->timestamp('last_checkpoint_at')->nullable()->after('inventory_sha256');
        });
        Schema::table('cloud_import_items', function (Blueprint $table): void {
            $table->unsignedInteger('position')->default(0)->after('cloud_import_session_id');
            $table->string('relative_path_hash', 64)->nullable()->after('external_id');
            $table->unsignedSmallInteger('attempt_count')->default(0)->after('state');
            $table->string('failure_code', 64)->nullable()->after('attempt_count');
            $table->index(['cloud_import_session_id', 'position'], 'cloud_import_batch_position_idx');
            $table->index(['cloud_import_session_id', 'relative_path_hash'], 'cloud_import_batch_path_hash_idx');
        });
    }

    public function down(): void
    {
        Schema::table('cloud_import_items', function (Blueprint $table): void {
            $table->dropIndex('cloud_import_batch_position_idx');
            $table->dropIndex('cloud_import_batch_path_hash_idx');
            $table->dropColumn(['position', 'relative_path_hash', 'attempt_count', 'failure_code']);
        });
        Schema::table('cloud_import_sessions', fn (Blueprint $table) => $table->dropColumn([
            'total_bytes', 'processed_count', 'checkpoint_position', 'chunk_size', 'inventory_sha256', 'last_checkpoint_at',
        ]));
    }
};
