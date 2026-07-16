<?php

use App\Models\User;

it('redirects guests away from archive storage', function (): void {
    $this->get(route('admin.archive-storage'))->assertRedirect(route('login'));
});

it('forbids authenticated non owners', function (): void {
    $viewer = User::factory()->create(['role' => 'viewer', 'email_verified_at' => now()]);

    $this->actingAs($viewer)->get(route('admin.archive-storage'))->assertForbidden();
});

it('allows an owner to inspect the read only storage contract', function (): void {
    $owner = User::factory()->create(['role' => 'owner', 'email_verified_at' => now()]);

    $this->actingAs($owner)->get(route('admin.archive-storage'))
        ->assertOk()
        ->assertSee('Archive Storage Foundation')
        ->assertSee('archive_originals')
        ->assertSee('PH_000001')
        ->assertSee('photos/000/PH_000001.jpg')
        ->assertSee('HTTP 403')
        ->assertDontSee('Upload media')
        ->assertDontSee('Delete original')
        ->assertDontSee('Replace original');
});

it('registers auth verified and owner middleware', function (): void {
    $route = app('router')->getRoutes()->getByName('admin.archive-storage');

    expect($route)->not->toBeNull()
        ->and($route?->gatherMiddleware())->toContain('auth')
        ->and($route?->gatherMiddleware())->toContain('verified')
        ->and($route?->gatherMiddleware())->toContain('owner');
});

it('registers no storage mutation method on the group 03 route', function (): void {
    $route = app('router')->getRoutes()->getByName('admin.archive-storage');

    expect($route?->methods())->toBe(['GET', 'HEAD']);
});
