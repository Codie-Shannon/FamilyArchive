<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('archive_id_sequences', function (Blueprint $table): void {
            $table->id();
            $table->string('media_type', 32)->unique();
            $table->unsignedBigInteger('last_value')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        throw new LogicException('The archive_id_sequences migration is forward-only.');
    }
};
