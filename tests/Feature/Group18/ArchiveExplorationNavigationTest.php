<?php

use App\Models\User;

function explorationUser(array $attributes = []): User
{
    return User::factory()->create([
        'role' => 'viewer',
        'account_state' => 'approved',
        'email_verified_at' => now(),
        ...$attributes,
    ]);
}

it('shows approved members the three focused archive destinations', function (): void {
    $member = explorationUser();

    $this->actingAs($member)
        ->get(route('archive.index'))
        ->assertOk()
        ->assertSee('aria-label="Explore archive"', false)
        ->assertSee(route('archive.index'), false)
        ->assertSee(route('archive.albums.index'), false)
        ->assertSee(route('archive.knowledge'), false)
        ->assertSee('Photos')
        ->assertSee('Albums</a>', false)
        ->assertSee('Search</a>', false);
});

it('keeps the public map navigation safe for signed-out visitors', function (): void {
    $this->get(route('public-discovery.map'))
        ->assertOk()
        ->assertSee('aria-label="Explore archive"', false)
        ->assertSee('Archive map', false)
        ->assertSee('Private sign in')
        ->assertDontSee('Photos</a>', false)
        ->assertDontSee('People</a>', false);
});

it('gives owners one consistent route into every archive workspace', function (string $routeName): void {
    $owner = explorationUser(['role' => 'owner']);

    $this->actingAs($owner)
        ->get(route($routeName))
        ->assertOk()
        ->assertSee('aria-label="Explore archive"', false)
        ->assertSee(route('archive.index'), false)
        ->assertSee(route('archive.albums.index'), false)
        ->assertSee(route('archive.knowledge'), false)
        ->assertSee('aria-current="page"', false);
})->with([
    'photos' => 'archive.index',
    'albums' => 'archive.albums.index',
    'places' => 'archive.locations.index',
    'people' => 'archive.people.index',
    'events' => 'archive.events.index',
    'branches' => 'archive.branches.index',
    'search' => 'archive.knowledge',
    'public map while signed in' => 'public-discovery.map',
]);

it('keeps the signed-in map inside the private archive shell', function (): void {
    $owner = explorationUser(['role' => 'owner']);

    $this->actingAs($owner)
        ->get(route('public-discovery.map'))
        ->assertOk()
        ->assertSee('Your archive')
        ->assertSee('>Work<', false)
        ->assertSee(route('archive.index'), false)
        ->assertDontSee('Public discovery navigation')
        ->assertDontSee('Private sign in');
});
