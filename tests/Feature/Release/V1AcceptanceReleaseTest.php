<?php

use App\Domain\Release\Services\AcceptanceMatrix;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

it('creates v1 acceptance and custodianship records', function () {
    foreach ([
        'pilot_feedback',
        'release_acceptance_runs',
        'custodian_designations',
        'custodianship_events',
    ] as $table) {
        expect(Schema::hasTable($table))->toBeTrue();
    }
});

it('blocks acceptance until operational and custodianship gates exist', function () {
    $run = app(AcceptanceMatrix::class)->record();

    expect(DB::table('release_acceptance_runs')->where('run_id', $run)->value('state'))->toBe('blocked')
        ->and(DB::table('release_acceptance_runs')->count())->toBe(1);
});

it('becomes ready only after every deterministic gate passes', function () {
    $owner = User::factory()->create(['role' => 'owner']);

    DB::table('pilot_feedback')->insert([
        'feedback_id' => fake()->uuid(),
        'submitted_by' => null,
        'area' => 'accessibility',
        'severity' => 'minor',
        'summary' => 'Fictional keyboard review note.',
        'state' => 'resolved',
        'resolution' => 'Fictional verification completed.',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    DB::table('backup_verifications')->insert([
        'verification_id' => fake()->uuid(),
        'backup_set' => 'fictional-isolated-restore',
        'result' => 'verified',
        'inventory' => json_encode(['records' => 10], JSON_THROW_ON_ERROR),
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    DB::table('storage_provider_statuses')->insert([
        'provider' => 'local',
        'state' => 'healthy',
        'capabilities' => json_encode(['private' => true], JSON_THROW_ON_ERROR),
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    DB::table('custodian_designations')->insert([
        'designation_id' => fake()->uuid(),
        'user_id' => $owner->id,
        'role' => 'primary',
        'state' => 'confirmed',
        'scope' => 'Fictional v1 acceptance proof',
        'designated_by' => $owner->id,
        'confirmed_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $run = app(AcceptanceMatrix::class)->record();

    expect(DB::table('release_acceptance_runs')->where('run_id', $run)->value('state'))->toBe('ready');
});

it('keeps the acceptance surface inside the verified owner boundary', function () {
    $this->withoutVite();

    $owner = User::factory()->create(['role' => 'owner', 'email_verified_at' => now()]);
    $viewer = User::factory()->create(['role' => 'viewer', 'email_verified_at' => now()]);

    $this->get(route('admin.release-acceptance'))->assertRedirect('/login');
    $this->actingAs($viewer)->get(route('admin.release-acceptance'))->assertForbidden();
    $this->actingAs($owner)
        ->get(route('admin.release-acceptance'))
        ->assertOk()
        ->assertSee('Family Archive v1.0 acceptance')
        ->assertSee('Honest human gates')
        ->assertSee('Not recorded — human action required')
        ->assertSee('Whole-system walkthrough')
        ->assertDontSee('AWS_SECRET_ACCESS_KEY')
        ->assertDontSee('WASABI_SECRET_ACCESS_KEY');
});

it('keeps final release metadata aligned', function () {
    expect(config('release.version'))->toBe('1.0.0')
        ->and(config('release.name'))->toBe('Family Archive v1.0')
        ->and(config('release.groups'))->toBe('45-46')
        ->and(config('release.status'))->toBe('Screenshot Group 05 implemented — acceptance evidence pending');
});
