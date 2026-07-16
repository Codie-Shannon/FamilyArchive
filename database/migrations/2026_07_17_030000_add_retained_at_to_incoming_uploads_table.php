<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('incoming_uploads', function (Blueprint $table): void {
            $table->timestamp('retained_at')->nullable()->after('source_file_retained');
        });
    }

    public function down(): void
    {
        Schema::table('incoming_uploads', function (Blueprint $table): void {
            $table->dropColumn('retained_at');
        });
    }
};
