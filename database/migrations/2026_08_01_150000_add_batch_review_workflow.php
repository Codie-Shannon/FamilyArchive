<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cloud_import_sessions', function (Blueprint $table): void {
            $table->string('review_state', 32)->default('not_ready')->index()->after('last_checkpoint_at');
            $table->unsignedInteger('reviewed_count')->default(0)->after('review_state');
            $table->unsignedInteger('attention_count')->default(0)->after('reviewed_count');
            $table->foreignId('reviewed_by')->nullable()->after('attention_count')->constrained('users')->nullOnDelete();
            $table->timestamp('review_completed_at')->nullable()->after('reviewed_by');
        });

        Schema::table('cloud_import_items', function (Blueprint $table): void {
            $table->foreignId('restoration_candidate_id')->nullable()->after('incoming_upload_id')->constrained('restoration_candidates')->nullOnDelete();
            $table->string('review_decision', 32)->nullable()->index()->after('restoration_candidate_id');
            $table->string('attention_code', 64)->nullable()->index()->after('review_decision');
            $table->foreignId('reviewed_by')->nullable()->after('attention_code')->constrained('users')->nullOnDelete();
            $table->timestamp('prepared_at')->nullable()->after('reviewed_by');
            $table->timestamp('reviewed_at')->nullable()->after('prepared_at');
        });
    }

    public function down(): void
    {
        Schema::table('cloud_import_items', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('restoration_candidate_id');
            $table->dropConstrainedForeignId('reviewed_by');
            $table->dropIndex(['review_decision']);
            $table->dropIndex(['attention_code']);
            $table->dropColumn(['review_decision', 'attention_code', 'prepared_at', 'reviewed_at']);
        });

        Schema::table('cloud_import_sessions', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('reviewed_by');
            $table->dropIndex(['review_state']);
            $table->dropColumn(['review_state', 'reviewed_count', 'attention_count', 'review_completed_at']);
        });
    }
};
