<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pilot_feedback', function (Blueprint $table): void {
            $table->id();
            $table->uuid('feedback_id')->unique();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('area', [
                'accessibility',
                'privacy',
                'onboarding',
                'archive',
                'upload',
                'conversation',
                'operations',
            ]);
            $table->enum('severity', ['observation', 'minor', 'major', 'blocking']);
            $table->text('summary');
            $table->enum('state', ['open', 'accepted', 'resolved', 'declined'])->default('open');
            $table->text('resolution')->nullable();
            $table->timestamps();
        });

        Schema::create('release_acceptance_runs', function (Blueprint $table): void {
            $table->id();
            $table->uuid('run_id')->unique();
            $table->string('version');
            $table->enum('state', ['draft', 'blocked', 'ready', 'accepted'])->default('draft');
            $table->json('gates');
            $table->foreignId('accepted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamps();
        });

        Schema::create('custodian_designations', function (Blueprint $table): void {
            $table->id();
            $table->uuid('designation_id')->unique();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->enum('role', ['primary', 'successor', 'emergency']);
            $table->enum('state', ['proposed', 'confirmed', 'revoked'])->default('proposed');
            $table->text('scope');
            $table->foreignId('designated_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('custodianship_events', function (Blueprint $table): void {
            $table->id();
            $table->uuid('event_id')->unique();
            $table->foreignId('custodian_designation_id')->constrained()->restrictOnDelete();
            $table->string('action');
            $table->text('reason');
            $table->foreignId('performed_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('custodianship_events');
        Schema::dropIfExists('custodian_designations');
        Schema::dropIfExists('release_acceptance_runs');
        Schema::dropIfExists('pilot_feedback');
    }
};
