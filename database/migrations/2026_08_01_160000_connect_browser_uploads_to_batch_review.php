<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('upload_sessions', function (Blueprint $table): void {
            $table->foreignId('cloud_import_session_id')
                ->nullable()
                ->unique()
                ->after('user_id')
                ->constrained('cloud_import_sessions')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('upload_sessions', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('cloud_import_session_id');
        });
    }
};
