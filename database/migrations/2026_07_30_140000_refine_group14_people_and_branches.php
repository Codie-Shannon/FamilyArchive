<?php

use App\Domain\Knowledge\Enums\KnowledgeReviewState;
use App\Domain\Knowledge\Enums\PersonDatePrecision;
use App\Domain\Knowledge\Enums\PersonNameCertainty;
use App\Domain\Media\Enums\StructuredDateConfidence;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('family_branches', 'metadata_revision')) {
            Schema::table('family_branches', function (Blueprint $table): void {
                $table->boolean('is_sensitive')->default(false)->after('description');
                $table->string('review_state', 32)
                    ->default(KnowledgeReviewState::Accepted->value)
                    ->after('is_sensitive');
                $table->string('confidence', 32)
                    ->default(StructuredDateConfidence::Unknown->value)
                    ->after('review_state');
                $table->text('source_note')->nullable()->after('confidence');
                $table->text('review_reason')->nullable()->after('source_note');
                $table->foreignId('created_by')
                    ->nullable()
                    ->after('review_reason')
                    ->constrained('users')
                    ->nullOnDelete();
                $table->foreignId('reviewed_by')
                    ->nullable()
                    ->after('created_by')
                    ->constrained('users')
                    ->nullOnDelete();
                $table->timestamp('reviewed_at')->nullable()->after('reviewed_by');
                $table->unsignedInteger('metadata_revision')->default(0)->after('reviewed_at');
                $table->index(['review_state', 'is_sensitive']);
            });
        }

        if (! Schema::hasColumn('archive_people', 'metadata_revision')) {
            Schema::table('archive_people', function (Blueprint $table): void {
                $table->string('name_certainty', 32)
                    ->default(PersonNameCertainty::Unknown->value)
                    ->after('alternate_names');
                $table->date('birth_on')->nullable()->after('name_certainty');
                $table->unsignedSmallInteger('birth_decade')->nullable()->after('birth_year');
                $table->string('birth_precision', 32)
                    ->default(PersonDatePrecision::Unknown->value)
                    ->after('birth_decade');
                $table->date('death_on')->nullable()->after('birth_precision');
                $table->unsignedSmallInteger('death_decade')->nullable()->after('death_year');
                $table->string('death_precision', 32)
                    ->default(PersonDatePrecision::Unknown->value)
                    ->after('death_decade');
                $table->string('fact_confidence', 32)
                    ->default(StructuredDateConfidence::Unknown->value)
                    ->after('identity_state');
                $table->text('source_note')->nullable()->after('fact_confidence');
                $table->string('review_state', 32)
                    ->default(KnowledgeReviewState::Accepted->value)
                    ->after('notes');
                $table->text('review_reason')->nullable()->after('review_state');
                $table->foreignId('created_by')
                    ->nullable()
                    ->after('review_reason')
                    ->constrained('users')
                    ->nullOnDelete();
                $table->foreignId('reviewed_by')
                    ->nullable()
                    ->after('created_by')
                    ->constrained('users')
                    ->nullOnDelete();
                $table->timestamp('reviewed_at')->nullable()->after('reviewed_by');
                $table->unsignedInteger('metadata_revision')->default(0)->after('reviewed_at');
                $table->index(['review_state', 'is_private', 'display_name']);
                $table->index(['family_branch_id', 'review_state']);
            });
        }

        if (! Schema::hasTable('archive_person_provenance_links')) {
            Schema::create('archive_person_provenance_links', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('archive_person_id')
                    ->constrained('archive_people')
                    ->restrictOnDelete();
                $table->foreignId('source_collection_id')
                    ->constrained('source_collections')
                    ->restrictOnDelete();
                $table->foreignId('scan_batch_id')
                    ->nullable()
                    ->constrained('scan_batches')
                    ->restrictOnDelete();
                $table->text('note')->nullable();
                $table->foreignId('attached_by')
                    ->constrained('users')
                    ->restrictOnDelete();
                $table->timestamps();
                $table->index(
                    ['archive_person_id', 'source_collection_id'],
                    'person_provenance_person_source_idx'
                );
            });
        }

        if (! Schema::hasTable('family_branch_provenance_links')) {
            Schema::create('family_branch_provenance_links', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('family_branch_id')
                    ->constrained('family_branches')
                    ->restrictOnDelete();
                $table->foreignId('source_collection_id')
                    ->constrained('source_collections')
                    ->restrictOnDelete();
                $table->foreignId('scan_batch_id')
                    ->nullable()
                    ->constrained('scan_batches')
                    ->restrictOnDelete();
                $table->text('note')->nullable();
                $table->foreignId('attached_by')
                    ->constrained('users')
                    ->restrictOnDelete();
                $table->timestamps();
                $table->index(
                    ['family_branch_id', 'source_collection_id'],
                    'branch_provenance_branch_source_idx'
                );
            });
        }

        if (! Schema::hasTable('archive_person_revisions')) {
            Schema::create('archive_person_revisions', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('archive_person_id')
                    ->constrained('archive_people')
                    ->restrictOnDelete();
                $table->unsignedInteger('revision_number');
                $table->foreignId('actor_user_id')
                    ->constrained('users')
                    ->restrictOnDelete();
                $table->unsignedInteger('from_revision');
                $table->unsignedInteger('to_revision');
                $table->json('changed_fields');
                $table->json('before_values');
                $table->json('after_values');
                $table->text('change_reason');
                $table->timestamp('created_at');
                $table->unique(
                    ['archive_person_id', 'revision_number'],
                    'person_revision_number_unique'
                );
            });
        }

        if (! Schema::hasTable('family_branch_revisions')) {
            Schema::create('family_branch_revisions', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('family_branch_id')
                    ->constrained('family_branches')
                    ->restrictOnDelete();
                $table->unsignedInteger('revision_number');
                $table->foreignId('actor_user_id')
                    ->constrained('users')
                    ->restrictOnDelete();
                $table->unsignedInteger('from_revision');
                $table->unsignedInteger('to_revision');
                $table->json('changed_fields');
                $table->json('before_values');
                $table->json('after_values');
                $table->text('change_reason');
                $table->timestamp('created_at');
                $table->unique(
                    ['family_branch_id', 'revision_number'],
                    'branch_revision_number_unique'
                );
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('family_branch_revisions');
        Schema::dropIfExists('archive_person_revisions');
        Schema::dropIfExists('family_branch_provenance_links');
        Schema::dropIfExists('archive_person_provenance_links');

        Schema::table('archive_people', function (Blueprint $table): void {
            $table->dropIndex(['review_state', 'is_private', 'display_name']);
            $table->dropIndex(['family_branch_id', 'review_state']);
            $table->dropConstrainedForeignId('created_by');
            $table->dropConstrainedForeignId('reviewed_by');
            $table->dropColumn([
                'name_certainty',
                'birth_on',
                'birth_decade',
                'birth_precision',
                'death_on',
                'death_decade',
                'death_precision',
                'fact_confidence',
                'source_note',
                'review_state',
                'review_reason',
                'reviewed_at',
                'metadata_revision',
            ]);
        });

        Schema::table('family_branches', function (Blueprint $table): void {
            $table->dropIndex(['review_state', 'is_sensitive']);
            $table->dropConstrainedForeignId('created_by');
            $table->dropConstrainedForeignId('reviewed_by');
            $table->dropColumn([
                'is_sensitive',
                'review_state',
                'confidence',
                'source_note',
                'review_reason',
                'reviewed_at',
                'metadata_revision',
            ]);
        });
    }
};
