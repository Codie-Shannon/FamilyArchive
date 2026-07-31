<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('storage_provider_verifications', function (Blueprint $table): void {
            $table->id();
            $table->string('provider', 24);
            $table->string('state', 24);
            $table->boolean('configuration_complete');
            $table->boolean('bucket_access');
            $table->boolean('versioning_enabled');
            $table->boolean('object_lock_enabled');
            $table->boolean('write_read_delete_verified');
            $table->string('safe_summary', 255);
            $table->timestamp('checked_at');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['provider', 'checked_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('storage_provider_verifications');
    }
};
