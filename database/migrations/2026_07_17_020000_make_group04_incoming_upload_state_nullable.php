<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('incoming_uploads', function (Blueprint $table): void {
            $table->char('sha256', 64)->nullable()->change();
        });
    }

    public function down(): void
    {
        throw new LogicException('The Group 04 incoming upload state migration is forward-only.');
    }
};
