<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media_metadata_revisions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('media_item_id')->constrained('media_items')->restrictOnDelete();
            $table->unsignedInteger('revision_number');
            $table->foreignId('actor_user_id')->constrained('users')->restrictOnDelete();
            $table->unsignedInteger('from_revision');
            $table->unsignedInteger('to_revision');
            $table->json('changed_fields');
            $table->json('before_values');
            $table->json('after_values');
            $table->string('change_reason', 500);
            $table->timestamp('created_at');
            $table->unique(['media_item_id', 'revision_number']);
            $table->index(['media_item_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media_metadata_revisions');
    }
};
