<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('storage_transfers', function (Blueprint $table): void {
            $table->id();
            $table->uuid('transfer_id')->unique();
            $table->foreignId('media_file_version_id')->constrained()->restrictOnDelete();
            $table->string('source_provider');
            $table->string('target_provider');
            $table->string('target_path');
            $table->unsignedBigInteger('expected_bytes');
            $table->string('expected_sha256', 64);
            $table->enum('state', ['planned', 'copying', 'verified', 'cutover', 'failed', 'rolled_back'])
                ->default('planned')
                ->index();
            $table->text('failure_reason')->nullable();
            $table->timestamps();
            $table->unique(['target_provider', 'target_path']);
        });

        Schema::create('integrity_manifests', function (Blueprint $table): void {
            $table->id();
            $table->uuid('manifest_id')->unique();
            $table->unsignedInteger('version');
            $table->string('sha256', 64);
            $table->unsignedInteger('record_count');
            $table->string('storage_path');
            $table->timestamps();
        });

        Schema::create('integrity_checks', function (Blueprint $table): void {
            $table->id();
            $table->uuid('check_id')->unique();
            $table->foreignId('media_file_version_id')->constrained()->restrictOnDelete();
            $table->enum('result', ['verified', 'missing', 'size_mismatch', 'hash_mismatch', 'unreadable', 'provider_error'])
                ->index();
            $table->json('observed');
            $table->timestamp('checked_at');
            $table->timestamps();
        });

        Schema::create('repair_cases', function (Blueprint $table): void {
            $table->id();
            $table->uuid('case_id')->unique();
            $table->foreignId('integrity_check_id')->constrained()->restrictOnDelete();
            $table->enum('state', ['open', 'candidate_found', 'approved', 'repaired', 'closed'])->default('open');
            $table->string('recovery_source')->nullable();
            $table->string('new_object_path')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('scan_imports', function (Blueprint $table): void {
            $table->id();
            $table->uuid('import_id')->unique();
            $table->foreignId('scan_batch_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('expected_count');
            $table->unsignedInteger('processed_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->enum('state', ['preflight', 'ready', 'running', 'paused', 'complete', 'failed'])
                ->default('preflight');
            $table->json('reconciliation')->nullable();
            $table->timestamps();
        });

        Schema::create('backup_verifications', function (Blueprint $table): void {
            $table->id();
            $table->uuid('verification_id')->unique();
            $table->string('backup_set');
            $table->enum('result', ['pending', 'verified', 'incomplete', 'mismatch', 'failed'])->default('pending');
            $table->json('inventory');
            $table->string('restore_namespace')->nullable();
            $table->unsignedInteger('recovery_point_minutes')->nullable();
            $table->unsignedInteger('recovery_time_minutes')->nullable();
            $table->timestamps();
        });

        Schema::create('operational_events', function (Blueprint $table): void {
            $table->id();
            $table->uuid('event_id')->unique();
            $table->enum('type', [
                'job_failure',
                'integrity_mismatch',
                'backup_failure',
                'provider_outage',
                'storage_pressure',
                'deployment',
                'recovery_drill',
            ])->index();
            $table->enum('severity', ['info', 'warning', 'critical']);
            $table->string('safe_summary');
            $table->json('metrics')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach ([
            'operational_events',
            'backup_verifications',
            'scan_imports',
            'repair_cases',
            'integrity_checks',
            'integrity_manifests',
            'storage_transfers',
        ] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
