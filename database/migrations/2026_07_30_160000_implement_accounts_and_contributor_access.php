<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_invitations', function (Blueprint $table): void {
            $table->id();
            $table->uuid('invitation_id')->unique();
            $table->string('email')->index();
            $table->string('name');
            $table->string('role', 32)->default('viewer');
            $table->foreignId('family_branch_id')->nullable()->constrained()->nullOnDelete();
            $table->string('token_hash', 64)->unique();
            $table->foreignId('invited_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('expires_at');
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
        });

        Schema::create('account_access_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('event_type', 64)->index();
            $table->json('previous_values')->nullable();
            $table->json('new_values')->nullable();
            $table->text('reason')->nullable();
            $table->timestamp('created_at')->useCurrent()->index();
        });

        Schema::table('media_items', function (Blueprint $table): void {
            $table->foreignId('family_branch_id')->nullable()->after('sensitivity_status')->constrained()->restrictOnDelete();
        });

        Schema::table('upload_sessions', function (Blueprint $table): void {
            $table->string('title')->nullable()->after('user_id');
            $table->text('source_context')->nullable()->after('title');
            $table->json('automation_preferences')->nullable()->after('source_context');
        });

        Schema::table('contributor_submissions', function (Blueprint $table): void {
            $table->foreignId('upload_session_id')->nullable()->after('user_id')->constrained('upload_sessions')->nullOnDelete();
            $table->foreignId('incoming_upload_id')->nullable()->after('upload_session_id')->constrained('incoming_uploads')->restrictOnDelete();
            $table->json('automation_preferences')->nullable()->after('proposed_metadata');
            $table->foreignId('reviewed_by')->nullable()->after('automation_preferences')->constrained('users')->restrictOnDelete();
            $table->text('reviewer_note')->nullable()->after('reviewed_by');
            $table->timestamp('reviewed_at')->nullable()->after('reviewer_note');
        });
    }

    public function down(): void
    {
        Schema::table('contributor_submissions', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('reviewed_by');
            $table->dropConstrainedForeignId('incoming_upload_id');
            $table->dropConstrainedForeignId('upload_session_id');
            $table->dropColumn(['automation_preferences', 'reviewer_note', 'reviewed_at']);
        });
        Schema::table('upload_sessions', fn (Blueprint $table) => $table->dropColumn([
            'title', 'source_context', 'automation_preferences',
        ]));
        Schema::table('media_items', fn (Blueprint $table) => $table->dropConstrainedForeignId('family_branch_id'));
        Schema::dropIfExists('account_access_events');
        Schema::dropIfExists('user_invitations');
    }
};
