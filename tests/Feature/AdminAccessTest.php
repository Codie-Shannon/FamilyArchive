<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('redirects guests away from the dashboard', function (): void {
    $response = $this->get(route('dashboard'));

    $response->assertRedirect(route('login'));
});

it('redirects guests away from the admin area', function (): void {
    $response = $this->get(route('admin.dashboard'));

    $response->assertRedirect(route('login'));
});

it('forbids authenticated non-owner users from the admin area', function (): void {
    $user = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    $user->forceFill([
        'role' => 'viewer',
    ])->save();

    $response = $this
        ->actingAs($user)
        ->get(route('admin.dashboard'));

    $response->assertForbidden();
});

it('allows the owner to access the admin area', function (): void {
    $owner = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    $owner->forceFill([
        'role' => 'owner',
    ])->save();

    $response = $this
        ->actingAs($owner)
        ->get(route('admin.dashboard'));

    $response->assertOk();
    $response->assertSee('Family Archive');
    $response->assertSee('Possible duplicates');
    $response->assertSee('Integrity warnings');
});

it('loads the application home page', function (): void {
    $response = $this->get(route('home'));

    $response->assertOk();
});
