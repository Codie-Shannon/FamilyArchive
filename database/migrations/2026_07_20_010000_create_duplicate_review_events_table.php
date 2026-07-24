<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('duplicate_review_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('duplicate_candidate_id')->constrained('duplicate_candidates')->restrictOnDelete();
            $table->string('previous_decision', 32)->nullable();
            $table->string('new_decision', 32);
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->text('reason')->nullable();
            $table->json('request_context')->nullable();
            $table->timestamp('decided_at');
            $table->timestamps();
            $table->index(['duplicate_candidate_id', 'decided_at']);
        });
    }

    public function down(): void
    {
        throw new LogicException('The duplicate_review_events migration is forward-only.');
    }
};
