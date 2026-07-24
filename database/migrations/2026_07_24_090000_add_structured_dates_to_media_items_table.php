<?php

use App\Domain\Media\Enums\DateConfidence;
use App\Domain\Media\Enums\DatePrecision;
use App\Domain\Media\Enums\DateReviewState;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('media_items', function (Blueprint $table): void {
            $table->string('date_precision', 32)
                ->default(DatePrecision::Unknown->value)
                ->after('canonical_date');
            $table->unsignedSmallInteger('date_year')
                ->nullable()
                ->after('date_precision');
            $table->string('date_review_state', 32)
                ->default(DateReviewState::Accepted->value)
                ->after('date_confidence');
            $table->text('date_source_note')
                ->nullable()
                ->after('date_review_state');
            $table->text('date_reason')
                ->nullable()
                ->after('date_source_note');
            $table->index(['date_precision', 'date_year']);
        });

        DB::table('media_items')
            ->whereNotNull('canonical_date')
            ->update([
                'date_precision' => DatePrecision::Exact->value,
                'date_confidence' => DateConfidence::Confirmed->value,
            ]);

        DB::table('media_items')
            ->whereNull('canonical_date')
            ->whereNotNull('estimated_decade')
            ->update([
                'date_precision' => DatePrecision::DecadeOnly->value,
                'date_confidence' => DateConfidence::Low->value,
            ]);
    }

    public function down(): void
    {
        Schema::table('media_items', function (Blueprint $table): void {
            $table->dropIndex(['date_precision', 'date_year']);
            $table->dropColumn([
                'date_precision',
                'date_year',
                'date_review_state',
                'date_source_note',
                'date_reason',
            ]);
        });
    }
};
