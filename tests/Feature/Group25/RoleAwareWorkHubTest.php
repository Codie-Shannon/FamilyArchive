<?php

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

beforeEach(function (): void {
    $this->withoutVite();
});

function sg25User(string $role = 'viewer'): User
{
    return User::factory()->create([
        'role' => $role,
        'account_state' => 'approved',
        'email_verified_at' => now(),
    ]);
}

function sg25Batch(User $user, int $attention = 0): void
{
    DB::table('cloud_import_sessions')->insert([
        'session_id' => (string) Str::uuid(),
        'cloud_import_connection_id' => null,
        'user_id' => $user->id,
        'provider' => 'manual_export',
        'state' => 'paused',
        'selected_count' => 12,
        'imported_count' => 12,
        'failed_count' => 0,
        'total_bytes' => 12000,
        'processed_count' => 12,
        'checkpoint_position' => 12,
        'chunk_size' => 500,
        'review_state' => 'ready',
        'reviewed_count' => 12 - $attention,
        'attention_count' => $attention,
        'source_manifest' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

it('restricts the work hub to operational roles', function (): void {
    $viewer = sg25User();
    $contributor = sg25User('contributor');

    $this->get(route('work.index'))->assertRedirect('/login');
    $this->actingAs($viewer)->get(route('work.index'))->assertForbidden();
    $this->actingAs($contributor)->get(route('work.index'))->assertForbidden();

    foreach (['trusted_contributor', 'admin', 'owner'] as $role) {
        $this->actingAs(sg25User($role))->get(route('work.index'))->assertOk();
    }
});

it('presents an exception-first owner workspace without routine approval language', function (): void {
    $owner = sg25User('owner');

    $this->actingAs($owner)->get(route('work.index'))
        ->assertOk()
        ->assertSee('Owner exceptions')
        ->assertSee('Access policy')
        ->assertSee('Routine work stays delegated')
        ->assertDontSee('Owner approval is required');
});

it('gives administrators routine operations without owner policy controls', function (): void {
    $admin = sg25User('admin');
    User::factory()->create(['role' => 'viewer', 'account_state' => 'pending', 'email_verified_at' => now()]);

    $this->actingAs($admin)->get(route('work.index'))
        ->assertOk()
        ->assertSee('Routine accounts')
        ->assertSee('Reported activity')
        ->assertDontSee('Access policy');
});

it('scopes trusted contributor batch summaries to the signed-in contributor', function (): void {
    $trusted = sg25User('trusted_contributor');
    $other = sg25User('trusted_contributor');
    sg25Batch($trusted, 2);
    sg25Batch($other, 7);

    $response = $this->actingAs($trusted)->get(route('work.index'))->assertOk();

    expect($response->viewData('summary')['intake_batches'])->toBe(1)
        ->and($response->viewData('summary')['intake_attention'])->toBe(2);

    $response->assertSee('Your batches')->assertDontSee('Owner exceptions');
});

it('keeps the sidebar focused on one operational destination', function (): void {
    $admin = sg25User('admin');

    $this->actingAs($admin)->get(route('work.index'))
        ->assertOk()
        ->assertSee('>Work<', false)
        ->assertDontSee('Intake &amp; Review', false)
        ->assertDontSee('Family Operations')
        ->assertDontSee('Command Centre');
});
