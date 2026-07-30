<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('public_identity_aliases', function (Blueprint $table): void {
            $table->id();
            $table->uuid('alias_id')->unique();
            $table->string('display_name');
            $table->string('moderation_fingerprint', 64)->index();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });
        Schema::create('public_direct_threads', function (Blueprint $table): void {
            $table->id();
            $table->uuid('thread_id')->unique();
            $table->foreignId('initiator_alias_id')->constrained('public_identity_aliases')->cascadeOnDelete();
            $table->foreignId('recipient_user_id')->constrained('users')->restrictOnDelete();
            $table->enum('state', ['pending', 'accepted', 'blocked', 'closed'])->default('pending');
            $table->timestamps();
        });
        Schema::create('encrypted_message_envelopes', function (Blueprint $table): void {
            $table->id();
            $table->uuid('envelope_id')->unique();
            $table->string('conversation_type');
            $table->unsignedBigInteger('conversation_id');
            $table->foreignId('sender_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('sender_alias_id')->nullable()->constrained('public_identity_aliases')->nullOnDelete();
            $table->unsignedSmallInteger('protocol_version');
            $table->text('ciphertext');
            $table->text('encrypted_content_key');
            $table->string('content_digest', 64);
            $table->timestamps();
            $table->index(['conversation_type', 'conversation_id'], 'encrypted_envelopes_conversation_index');
        });
        Schema::create('message_attachments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('encrypted_message_envelope_id')->constrained()->cascadeOnDelete();
            $table->string('storage_key');
            $table->string('original_name');
            $table->string('mime_type');
            $table->unsignedBigInteger('bytes');
            $table->string('checksum_sha256', 64);
            $table->enum('scan_state', ['pending', 'clean', 'rejected'])->default('pending');
            $table->timestamps();
        });
        Schema::create('guidance_bot_interactions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('interaction_id')->unique();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->text('redacted_prompt');
            $table->text('redacted_response')->nullable();
            $table->boolean('private_archive_accessed')->default(false);
            $table->timestamps();
        });
        Schema::create('messaging_bridge_deliveries', function (Blueprint $table): void {
            $table->id();
            $table->enum('provider', ['whatsapp', 'messenger']);
            $table->string('provider_message_id')->nullable();
            $table->enum('direction', ['inbound', 'outbound']);
            $table->enum('state', ['queued', 'sent', 'delivered', 'failed', 'quarantined'])->default('queued');
            $table->json('safe_metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('messaging_bridge_deliveries');
        Schema::dropIfExists('guidance_bot_interactions');
        Schema::dropIfExists('message_attachments');
        Schema::dropIfExists('encrypted_message_envelopes');
        Schema::dropIfExists('public_direct_threads');
        Schema::dropIfExists('public_identity_aliases');
    }
};
