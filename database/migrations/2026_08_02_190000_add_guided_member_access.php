<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('email')->nullable()->change();
            $table->string('username', 80)->nullable()->unique()->after('name');
        });

        Schema::table('user_invitations', function (Blueprint $table): void {
            $table->string('email')->nullable()->change();
            $table->string('username', 80)->nullable()->index()->after('name');
            $table->string('purpose', 24)->default('setup')->index()->after('role');
            $table->foreignId('target_user_id')->nullable()->after('purpose')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('user_invitations', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('target_user_id');
            $table->dropColumn(['username', 'purpose']);
            $table->string('email')->nullable(false)->change();
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->dropUnique(['username']);
            $table->dropColumn('username');
            $table->string('email')->nullable(false)->change();
        });
    }
};
