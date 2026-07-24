<?php

use App\Domain\Provenance\Enums\SourceCollectionType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('source_collections', function (Blueprint $table): void {
            $table->id();
            $table->string('source_id', 40)->unique();
            $table->string('type', 32)
                ->default(SourceCollectionType::Collection->value)
                ->index();
            $table->string('name', 160);
            $table->text('description')->nullable();
            $table->string('physical_reference', 255)->nullable();
            $table->foreignId('created_by')
                ->constrained('users')
                ->restrictOnDelete();
            $table->timestamps();
        });

        Schema::create('scan_batches', function (Blueprint $table): void {
            $table->id();
            $table->string('scan_batch_id', 40)->unique();
            $table->foreignId('source_collection_id')
                ->constrained('source_collections')
                ->restrictOnDelete();
            $table->string('label', 160);
            $table->date('scanned_on')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')
                ->constrained('users')
                ->restrictOnDelete();
            $table->timestamps();
            $table->index(['source_collection_id', 'scanned_on']);
        });

        Schema::create('media_provenance_links', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('media_item_id')
                ->constrained('media_items')
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
            $table->index(['media_item_id', 'source_collection_id']);
            $table->unique(
                ['media_item_id', 'source_collection_id', 'scan_batch_id'],
                'media_provenance_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media_provenance_links');
        Schema::dropIfExists('scan_batches');
        Schema::dropIfExists('source_collections');
    }
};
