<?php

use App\Models\User;
use Database\Seeders\Group02DemoSeeder;

it('redirects guests away from the archive schema page', function (): void {
    $this->get(route('admin.archive-schema'))
        ->assertRedirect(route('login'));
});

it('forbids authenticated non-owner users', function (): void {
    $viewer = User::factory()->create([
        'role' => 'viewer',
        'email_verified_at' => now(),
    ]);

    $this->actingAs($viewer)
        ->get(route('admin.archive-schema'))
        ->assertForbidden();
});

it('allows the owner to inspect every read-only schema view', function (string $view, string $expectedText): void {
    $owner = User::factory()->create([
        'role' => 'owner',
        'email_verified_at' => now(),
    ]);

    $this->actingAs($owner)
        ->get(route('admin.archive-schema', ['view' => $view]))
        ->assertOk()
        ->assertSee('Archive Schema')
        ->assertSee($expectedText)
        ->assertDontSee('Delete original')
        ->assertDontSee('Replace original')
        ->assertDontSee('Upload media');
})->with([
    'overview' => ['overview', 'Core archive schema'],
    'media item' => ['media-item', 'No file paths on the archive record'],
    'incoming upload' => ['incoming-upload', 'Separate until approval'],
    'file versions' => ['file-versions', 'Original and viewing versions are separate records'],
    'contracts' => ['contracts', 'public_highlight_approved'],
    'access boundary' => ['access-boundary', 'Authenticated non-owner'],
]);

it('renders the seeded fictional records without exposing storage paths', function (): void {
    $originalEnvironment = app()->environment();
    app()->instance('env', 'local');

    try {
        $this->seed(Group02DemoSeeder::class);
    } finally {
        app()->instance('env', $originalEnvironment);
    }

    $owner = User::query()->where('email', 'archive-owner@example.test')->firstOrFail();

    $this->actingAs($owner)
        ->get(route('admin.archive-schema', ['view' => 'media-item']))
        ->assertOk()
        ->assertSee('FA-DEMO-00000001')
        ->assertSee('Fictional Archive Scene')
        ->assertDontSee('demo/archive/');

    $this->actingAs($owner)
        ->get(route('admin.archive-schema', ['view' => 'file-versions']))
        ->assertOk()
        ->assertSee('original')
        ->assertSee('web_display')
        ->assertSee('thumbnail')
        ->assertDontSee('demo/archive/');
});

it('registers the exact authentication and owner middleware on the schema route', function (): void {
    $route = app('router')->getRoutes()->getByName('admin.archive-schema');

    expect($route)->not->toBeNull()
        ->and($route?->gatherMiddleware())->toContain('auth')
        ->and($route?->gatherMiddleware())->toContain('verified')
        ->and($route?->gatherMiddleware())->toContain('owner');
});
