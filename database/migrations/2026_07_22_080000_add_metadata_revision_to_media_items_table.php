<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void { Schema::table('media_items', function (Blueprint $table): void { $table->unsignedInteger('metadata_revision')->default(0)->after('story'); }); }
 public function down(): void { Schema::table('media_items', function (Blueprint $table): void { $table->dropColumn('metadata_revision'); }); }
};
