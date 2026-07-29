<?php

use App\Domain\Knowledge\Enums\KnowledgeReviewState;
use App\Domain\Media\Enums\DatePrecision;
use App\Domain\Media\Enums\StructuredDateConfidence;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('archive_locations', 'metadata_revision')) {
            Schema::table('archive_locations', function (Blueprint $table): void {
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

        if (! Schema::hasColumn('archive_events', 'metadata_revision')) {
            Schema::table('archive_events', function (Blueprint $table): void {
                $table->string('date_precision', 32)
                    ->default(DatePrecision::Unknown->value)
                    ->after('ends_on');
                $table->unsignedSmallInteger('date_year')->nullable()->after('date_precision');
                $table->unsignedSmallInteger('estimated_decade')->nullable()->after('date_year');
                $table->string('date_confidence', 32)
                    ->default(StructuredDateConfidence::Unknown->value)
                    ->after('estimated_decade');
                $table->text('date_source_note')->nullable()->after('date_confidence');
                $table->string('review_state', 32)
                    ->default(KnowledgeReviewState::Accepted->value)
                    ->after('description');
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
                $table->index(['review_state', 'date_precision', 'date_year']);
            });
        }

        if (! Schema::hasColumn('archive_event_media', 'reviewed_by')) {
            Schema::table('archive_event_media', function (Blueprint $table): void {
                $table->foreignId('reviewed_by')
                    ->nullable()
                    ->after('source_note')
                    ->constrained('users')
                    ->nullOnDelete();
                $table->timestamp('reviewed_at')->nullable()->after('reviewed_by');
            });
        }

        if (! Schema::hasTable('archive_event_provenance_links')) {
            Schema::create('archive_event_provenance_links', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('archive_event_id')
                    ->constrained('archive_events')
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
            });
        }

        if (! Schema::hasIndex('archive_event_provenance_links', ['archive_event_id', 'source_collection_id'])) {
            Schema::table('archive_event_provenance_links', function (Blueprint $table): void {
                $table->index(
                    ['archive_event_id', 'source_collection_id'],
                    'event_provenance_event_source_idx'
                );
            });
        }

        if (! Schema::hasTable('archive_event_revisions')) {
            Schema::create('archive_event_revisions', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('archive_event_id')
                    ->constrained('archive_events')
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
                    ['archive_event_id', 'revision_number'],
                    'event_revision_number_unique'
                );
            });
        }

        if (! Schema::hasTable('archive_location_revisions')) {
            Schema::create('archive_location_revisions', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('archive_location_id')
                    ->constrained('archive_locations')
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
                    ['archive_location_id', 'revision_number'],
                    'location_revision_number_unique'
                );
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('archive_location_revisions');
        Schema::dropIfExists('archive_event_revisions');
        Schema::dropIfExists('archive_event_provenance_links');

        Schema::table('archive_event_media', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('reviewed_by');
            $table->dropColumn('reviewed_at');
        });

        Schema::table('archive_events', function (Blueprint $table): void {
            $table->dropIndex(['review_state', 'date_precision', 'date_year']);
            $table->dropConstrainedForeignId('created_by');
            $table->dropConstrainedForeignId('reviewed_by');
            $table->dropColumn([
                'date_precision',
                'date_year',
                'estimated_decade',
                'date_confidence',
                'date_source_note',
                'review_state',
                'review_reason',
                'reviewed_at',
                'metadata_revision',
            ]);
        });

        Schema::table('archive_locations', function (Blueprint $table): void {
            $table->dropIndex(['review_state', 'is_sensitive']);
            $table->dropConstrainedForeignId('created_by');
            $table->dropConstrainedForeignId('reviewed_by');
            $table->dropColumn([
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
