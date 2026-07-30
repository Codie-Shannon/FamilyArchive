<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('community_spaces', function (Blueprint $table): void {
            $table->id();
            $table->uuid('space_id')->unique();
            $table->string('name');
            $table->enum('visibility', ['family', 'invite', 'public'])->default('family');
            $table->foreignId('owner_id')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });
        Schema::create('community_channels', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('community_space_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->enum('kind', ['text', 'voice', 'announcements']);
            $table->json('permission_overrides')->nullable();
            $table->timestamps();
        });
        Schema::create('community_memberships', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('community_space_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('role', ['owner', 'moderator', 'member', 'guest'])->default('member');
            $table->timestamp('suspended_at')->nullable();
            $table->timestamps();
            $table->unique(['community_space_id', 'user_id']);
        });
        Schema::create('community_presence', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('community_channel_id')->nullable()->constrained()->cascadeOnDelete();
            $table->enum('state', ['online', 'away', 'offline'])->default('offline');
            $table->timestamp('last_seen_at');
            $table->timestamp('typing_until')->nullable();
            $table->unique(['user_id', 'community_channel_id']);
        });
        Schema::create('voice_messages', function (Blueprint $table): void {
            $table->id();
            $table->uuid('message_id')->unique();
            $table->foreignId('community_channel_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('storage_key');
            $table->unsignedInteger('duration_seconds');
            $table->string('mime_type');
            $table->string('checksum_sha256', 64);
            $table->enum('moderation_state', ['pending', 'allowed', 'blocked'])->default('pending');
            $table->timestamps();
        });
        Schema::create('voice_call_sessions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('call_id')->unique();
            $table->foreignId('community_channel_id')->constrained()->cascadeOnDelete();
            $table->foreignId('started_by')->constrained('users')->restrictOnDelete();
            $table->enum('state', ['signalling', 'active', 'ended', 'failed'])->default('signalling');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->json('safe_diagnostics')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('voice_call_sessions');
        Schema::dropIfExists('voice_messages');
        Schema::dropIfExists('community_presence');
        Schema::dropIfExists('community_memberships');
        Schema::dropIfExists('community_channels');
        Schema::dropIfExists('community_spaces');
    }
};
