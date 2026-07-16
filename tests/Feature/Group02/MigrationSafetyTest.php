<?php

it('keeps every Group 02 migration forward-only and non-destructive', function (string $migrationFile): void {
    $contents = file_get_contents(database_path('migrations/'.$migrationFile));

    expect($contents)->not->toBeFalse()
        ->and($contents)->toContain('restrictOnDelete')
        ->and($contents)->toContain('forward-only')
        ->and($contents)->not->toContain('cascadeOnDelete')
        ->and($contents)->not->toContain('Schema::drop')
        ->and($contents)->not->toContain('dropIfExists')
        ->and($contents)->not->toContain('migrate:fresh')
        ->and($contents)->not->toContain('rollback');
})->with([
    'media items' => '2026_07_16_235000_create_media_items_table.php',
    'incoming uploads' => '2026_07_16_235100_create_incoming_uploads_table.php',
    'media file versions' => '2026_07_16_235200_create_media_file_versions_table.php',
]);
