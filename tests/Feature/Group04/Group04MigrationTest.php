<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

it('supports null hashes while preserving the existing retained-source default', function (): void {
    expect(Schema::hasColumns('incoming_uploads', ['sha256', 'source_file_retained']))->toBeTrue();

    DB::table('users')->insert([
        'name' => 'Archive Owner',
        'email' => 'owner-group04@example.test',
        'password' => 'x',
        'role' => 'owner',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('incoming_uploads')->insert([
        'upload_id' => 'UP_01HZX000000000000000000000',
        'uploader_id' => 1,
        'original_filename' => 'fictional.png',
        'incoming_path' => 'incoming/UP_01HZX000000000000000000000/fictional.png',
        'mime_type' => 'image/png',
        'extension' => 'png',
        'file_size_bytes' => 68,
        'width' => 1,
        'height' => 1,
        'processing_status' => 'pending',
        'review_status' => 'pending_review',
        'duplicate_status' => 'not_checked',
        'submitted_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $row = DB::table('incoming_uploads')->first();

    expect($row->sha256)->toBeNull()
        ->and((bool) $row->source_file_retained)->toBeTrue();
});
