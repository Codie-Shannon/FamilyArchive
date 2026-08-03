<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('photo_split_regions', function (Blueprint $table): void {
            $table->unsignedSmallInteger('rotation_degrees')->default(0)->after('height_basis_points');
        });
    }

    public function down(): void
    {
        Schema::table('photo_split_regions', function (Blueprint $table): void {
            $table->dropColumn('rotation_degrees');
        });
    }
};
