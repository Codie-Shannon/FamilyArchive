<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->enum('account_state', ['pending', 'approved', 'rejected', 'suspended'])->default('approved')->index();
            $table->foreignId('family_branch_id')->nullable()->constrained()->nullOnDelete();
            $table->text('family_connection')->nullable();
        });

        Schema::table('media_items', function (Blueprint $table): void {
            $table->boolean('contains_living_person')->default(false);
            $table->boolean('contains_child')->default(false);
        });

        Schema::create('original_access_grants', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('media_item_id')->constrained()->restrictOnDelete();
            $table->foreignId('granted_by')->constrained('users')->restrictOnDelete();
            $table->text('reason');
            $table->timestamp('effective_at');
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->text('revocation_reason')->nullable();
            $table->timestamps();
        });

        Schema::create('contributor_submissions', function (Blueprint $table): void {
            $table->id();
            $table->string('submission_id')->unique();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->enum('status', ['pending', 'retained', 'possible_duplicate', 'needs_info', 'accepted', 'rejected'])->default('pending')->index();
            $table->string('original_name');
            $table->text('source_context');
            $table->json('proposed_metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('upload_templates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->json('defaults');
            $table->timestamps();
        });

        Schema::create('upload_sessions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('session_id')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('expected_files');
            $table->unsignedInteger('received_files')->default(0);
            $table->enum('status', ['open', 'paused', 'complete', 'failed'])->default('open');
            $table->timestamp('expires_at');
            $table->timestamps();
        });

        Schema::create('archive_stories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('media_item_id')->constrained()->restrictOnDelete();
            $table->foreignId('author_id')->constrained('users')->restrictOnDelete();
            $table->text('body');
            $table->enum('visibility', ['private', 'family', 'branch', 'public'])->default('family');
            $table->timestamps();
        });

        Schema::create('conversation_threads', function (Blueprint $table): void {
            $table->id();
            $table->uuid('thread_id')->unique();
            $table->string('subject');
            $table->enum('scope', ['family', 'branch', 'entity', 'public'])->index();
            $table->string('entity_type')->nullable();
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->boolean('is_locked')->default(false);
            $table->timestamps();
        });

        Schema::create('conversation_messages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('conversation_thread_id')->constrained()->cascadeOnDelete();
            $table->foreignId('author_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('conversation_messages')->nullOnDelete();
            $table->text('body');
            $table->enum('moderation_state', ['visible', 'reported', 'hidden'])->default('visible')->index();
            $table->timestamps();
        });

        Schema::create('anonymous_messages', function (Blueprint $table): void {
            $table->id();
            $table->uuid('message_id')->unique();
            $table->string('correlation_token', 64)->index();
            $table->string('reply_email')->nullable();
            $table->string('subject');
            $table->text('body');
            $table->enum('moderation_state', ['pending', 'spam', 'accepted', 'blocked'])->default('pending')->index();
            $table->string('source_fingerprint', 64);
            $table->timestamps();
        });

        Schema::create('metadata_suggestions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('media_item_id')->constrained()->restrictOnDelete();
            $table->foreignId('suggested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('field');
            $table->text('suggested_value');
            $table->text('reason');
            $table->enum('state', ['pending', 'accepted', 'rejected'])->default('pending');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach ([
            'metadata_suggestions',
            'anonymous_messages',
            'conversation_messages',
            'conversation_threads',
            'archive_stories',
            'upload_sessions',
            'upload_templates',
            'contributor_submissions',
            'original_access_grants',
        ] as $table) {
            Schema::dropIfExists($table);
        }

        Schema::table('media_items', fn (Blueprint $table) => $table->dropColumn([
            'contains_living_person',
            'contains_child',
        ]));
        Schema::table('users', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('family_branch_id');
            $table->dropColumn(['account_state', 'family_connection']);
        });
    }
};
