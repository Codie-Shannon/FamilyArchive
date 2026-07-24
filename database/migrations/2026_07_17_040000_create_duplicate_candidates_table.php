<?php

use App\Domain\Duplicates\Enums\DuplicateCandidateReviewState;
use App\Domain\Duplicates\Enums\DuplicateMatchMethod;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('duplicate_candidates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('incoming_upload_id')->constrained('incoming_uploads')->restrictOnDelete();
            $table->foreignId('matched_incoming_upload_id')->nullable()->constrained('incoming_uploads')->restrictOnDelete();
            $table->foreignId('matched_media_file_version_id')->nullable()->constrained('media_file_versions')->restrictOnDelete();
            $table->string('match_method', 32)->default(DuplicateMatchMethod::ExactSha256->value);
            $table->char('matched_sha256', 64);
            $table->decimal('confidence', 5, 4)->default(1.0000);
            $table->string('review_state', 32)->default(DuplicateCandidateReviewState::PendingReview->value)->index();
            $table->timestamp('detected_at');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->string('resolution', 64)->nullable();
            $table->timestamps();

            $table->unique(['incoming_upload_id', 'matched_incoming_upload_id'], 'duplicate_candidate_upload_target_unique');
            $table->unique(['incoming_upload_id', 'matched_media_file_version_id'], 'duplicate_candidate_version_target_unique');
            $table->index(['matched_sha256', 'review_state']);
        });

        $driver = DB::connection()->getDriverName();
        if ($driver === 'sqlite') {
            DB::unprepared("CREATE TRIGGER duplicate_candidates_target_insert BEFORE INSERT ON duplicate_candidates BEGIN SELECT CASE WHEN ((NEW.matched_incoming_upload_id IS NULL) = (NEW.matched_media_file_version_id IS NULL)) OR NEW.incoming_upload_id = NEW.matched_incoming_upload_id THEN RAISE(ABORT, 'duplicate candidate requires exactly one non-self target') END; END;");
            DB::unprepared("CREATE TRIGGER duplicate_candidates_target_update BEFORE UPDATE ON duplicate_candidates BEGIN SELECT CASE WHEN ((NEW.matched_incoming_upload_id IS NULL) = (NEW.matched_media_file_version_id IS NULL)) OR NEW.incoming_upload_id = NEW.matched_incoming_upload_id THEN RAISE(ABORT, 'duplicate candidate requires exactly one non-self target') END; END;");
        } elseif (in_array($driver, ['mysql', 'mariadb'], true)) {
            DB::statement('ALTER TABLE duplicate_candidates ADD CONSTRAINT duplicate_candidates_exactly_one_target CHECK (((matched_incoming_upload_id IS NOT NULL) + (matched_media_file_version_id IS NOT NULL)) = 1)');
            DB::statement('ALTER TABLE duplicate_candidates ADD CONSTRAINT duplicate_candidates_no_self_match CHECK (matched_incoming_upload_id IS NULL OR incoming_upload_id <> matched_incoming_upload_id)');
        } elseif ($driver === 'pgsql') {
            DB::statement('ALTER TABLE duplicate_candidates ADD CONSTRAINT duplicate_candidates_exactly_one_target CHECK (num_nonnulls(matched_incoming_upload_id, matched_media_file_version_id) = 1)');
            DB::statement('ALTER TABLE duplicate_candidates ADD CONSTRAINT duplicate_candidates_no_self_match CHECK (matched_incoming_upload_id IS NULL OR incoming_upload_id <> matched_incoming_upload_id)');
        }
    }

    public function down(): void
    {
        throw new LogicException('The duplicate_candidates migration is forward-only.');
    }
};
