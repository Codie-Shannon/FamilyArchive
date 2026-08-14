<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('archive_photo_split_groups', function (Blueprint $table): void {
            $table->timestamp('gallery_approved_at')->nullable()->after('source_basis');
            $table->string('gallery_archive_id', 32)->nullable()->after('gallery_approved_at');
        });

        DB::table('archive_photo_split_groups')->orderBy('id')->chunkById(100, function ($groups): void {
            foreach ($groups as $group) {
                $source = DB::table('media_items')->where('id', $group->source_media_item_id)->first(['archive_id', 'approved_at']);
                $approvedAt = $source->approved_at ?? DB::table('archive_photo_split_members')
                    ->join('media_items', 'archive_photo_split_members.media_item_id', '=', 'media_items.id')
                    ->where('archive_photo_split_members.archive_photo_split_group_id', $group->id)
                    ->orderBy('archive_photo_split_members.position')
                    ->value('media_items.approved_at');

                DB::table('archive_photo_split_groups')->where('id', $group->id)->update([
                    'gallery_approved_at' => $approvedAt,
                    'gallery_archive_id' => $source->archive_id,
                ]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('archive_photo_split_groups', function (Blueprint $table): void {
            $table->dropColumn(['gallery_approved_at', 'gallery_archive_id']);
        });
    }
};
