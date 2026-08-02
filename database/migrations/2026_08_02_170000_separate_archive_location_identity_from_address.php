<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('archive_locations', function (Blueprint $table): void {
            $table->string('subtitle')->nullable()->after('label');
            $table->string('address', 500)->nullable()->after('subtitle');
        });
    }

    public function down(): void
    {
        Schema::table('archive_locations', function (Blueprint $table): void {
            $table->dropColumn(['subtitle', 'address']);
        });
    }
};
