<?php

use App\Models\User;

function sg16Member(array $attributes = []): User
{
    return User::factory()->create([
        'role' => 'viewer',
        'account_state' => 'approved',
        'email_verified_at' => now(),
        ...$attributes,
    ]);
}

it('reduces viewer navigation to home archive and messages', function (): void {
    $viewer = sg16Member(['name' => 'Aroha Raukawa']);

    $this->actingAs($viewer)->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Welcome back, Aroha')
        ->assertSee('Your archive')
        ->assertSee('Home')
        ->assertSee('Archive')
        ->assertSee('Messages')
        ->assertDontSee('Contribute</', false)
        ->assertDontSee('Family Community')
        ->assertDontSee('Public Home')
        ->assertDontSee('Archive Map')
        ->assertSee('View public site');
});

it('adds contribute only for roles allowed to upload', function (): void {
    $contributor = sg16Member(['role' => 'contributor']);
    $viewer = sg16Member();

    $this->actingAs($contributor)->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Contribute')
        ->assertSee('Contribute media');

    $this->actingAs($viewer)->get(route('dashboard'))
        ->assertOk()
        ->assertDontSee('Contribute media');
});

it('keeps family activity reachable from home without a separate sidebar item', function (): void {
    $member = sg16Member();

    $this->actingAs($member)->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Family activity')
        ->assertSee(route('community.index'), false);

    $this->actingAs($member)->get(route('community.index'))
        ->assertOk()
        ->assertSee('Home · Family activity')
        ->assertSee('Back to Home');
});

it('presents archive exploration as tabs instead of permanent sidebar links', function (): void {
    $member = sg16Member();

    $this->actingAs($member)->get(route('archive.index'))
        ->assertOk()
        ->assertSee('Archive views')
        ->assertSee('Photos')
        ->assertSee('Places & map', false)
        ->assertSee(route('public-discovery.map'), false)
        ->assertDontSee('People</a>', false);
});

it('keeps owner tooling separate from the member navigation layer', function (): void {
    $owner = sg16Member(['role' => 'owner']);

    $this->actingAs($owner)->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Owner tools')
        ->assertSee('Manage archive')
        ->assertSee('SG17');
});
