<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

it('adds the nullable retained_at verification timestamp', function () {
    expect(Schema::hasColumn('incoming_uploads', 'retained_at'))->toBeTrue();
});
