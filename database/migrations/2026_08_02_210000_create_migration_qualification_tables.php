<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('migration_qualification_runs', function (Blueprint $table): void {
            $table->id();
            $table->uuid('qualification_id')->unique();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->enum('state', ['planned', 'running', 'interrupted', 'reconciling', 'qualified', 'failed'])->default('planned')->index();
            $table->unsignedInteger('target_count');
            $table->unsignedSmallInteger('chunk_size');
            $table->unsignedInteger('completed_count')->default(0);
            $table->unsignedInteger('checkpoint_count')->default(0);
            $table->unsignedInteger('isolated_failures')->default(0);
            $table->unsignedInteger('recovered_failures')->default(0);
            $table->unsignedInteger('duplicate_skips')->default(0);
            $table->unsignedSmallInteger('restart_count')->default(0);
            $table->string('manifest_sha256', 64);
            $table->string('reconciliation_sha256', 64)->nullable();
            $table->json('qualification_profile');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('last_checkpoint_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('migration_qualification_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('migration_qualification_run_id');
            $table->unsignedInteger('position');
            $table->string('fingerprint', 64);
            $table->enum('state', ['pending', 'processed', 'failed', 'recovered'])->default('pending')->index();
            $table->unsignedSmallInteger('attempt_count')->default(0);
            $table->unsignedSmallInteger('checkpoint_number')->nullable();
            $table->timestamps();
            $table->unique(['migration_qualification_run_id', 'position'], 'migration_qualification_position_unique');
            $table->foreign('migration_qualification_run_id', 'migration_qualification_item_run_fk')
                ->references('id')->on('migration_qualification_runs')->cascadeOnDelete();
        });

        Schema::create('migration_qualification_checkpoints', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('migration_qualification_run_id');
            $table->unsignedSmallInteger('checkpoint_number');
            $table->unsignedInteger('first_position');
            $table->unsignedInteger('last_position');
            $table->unsignedSmallInteger('item_count');
            $table->unsignedSmallInteger('exception_count')->default(0);
            $table->string('checkpoint_sha256', 64);
            $table->timestamps();
            $table->unique(['migration_qualification_run_id', 'checkpoint_number'], 'migration_qualification_checkpoint_unique');
            $table->foreign('migration_qualification_run_id', 'migration_qualification_checkpoint_run_fk')
                ->references('id')->on('migration_qualification_runs')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('migration_qualification_checkpoints');
        Schema::dropIfExists('migration_qualification_items');
        Schema::dropIfExists('migration_qualification_runs');
    }
};
