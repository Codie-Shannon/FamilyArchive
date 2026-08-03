<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function sg17Owner(array $attributes = []): User
{
    $owner = User::factory()->create(array_merge([
        'email_verified_at' => now(),
        'account_state' => 'approved',
    ], $attributes));

    $owner->forceFill(['role' => 'owner'])->save();

    return $owner;
}

it('gives the owner one command centre with four focused views', function (): void {
    $owner = sg17Owner();

    $this->actingAs($owner)->get(route('admin.dashboard'))
        ->assertOk()
        ->assertSee('Command Centre')
        ->assertSee('Overview')
        ->assertSee('Work queue')
        ->assertSee('Family & access')
        ->assertSee('System & storage');
});

it('keeps the command centre behind the owner boundary', function (): void {
    $member = sg17Owner(['role' => 'viewer']);
    $member->forceFill(['role' => 'viewer'])->save();

    $this->actingAs($member)->get(route('admin.dashboard'))->assertForbidden();
});

it('replaces the permanent owner menu with one command centre destination', function (): void {
    $owner = sg17Owner();

    $this->actingAs($owner)->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Work')
        ->assertDontSee('Archive Administration')
        ->assertDontSee('Archive Schema')
        ->assertDontSee('Restoration Workspace')
        ->assertDontSee('Integrity Operations');
});

it('groups the established specialist workflows without removing their routes', function (string $view, array $labels): void {
    $owner = sg17Owner();
    $response = $this->actingAs($owner)->get(route('admin.dashboard', ['view' => $view]));

    $response->assertOk();

    foreach ($labels as $label) {
        $response->assertSee($label, false);
    }
})->with([
    'work queue' => ['queue', ['Accounts awaiting approval', 'Intake batches', 'Possible duplicates', 'Open repair cases']],
    'family and access' => ['family', ['Accounts & contributors', 'Archive knowledge', 'Community & privacy', 'Public experience']],
    'system and storage' => ['system', ['Storage & integrity', 'Intake & preservation', 'Restoration & intelligence', 'Production & release']],
]);

it('falls back to the overview for an unknown view', function (): void {
    $owner = sg17Owner();

    $this->actingAs($owner)->get(route('admin.dashboard', ['view' => 'unexpected']))
        ->assertOk()
        ->assertSee('Start with decisions, not dashboards');
});
