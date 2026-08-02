<?php

use App\Domain\CloudImport\Models\MigrationQualificationRun;
use App\Domain\CloudImport\Services\ArchiveMigrationQualification;
use App\Models\User;
use Illuminate\Support\Facades\DB;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

it('qualifies a deterministic thirty thousand entry manifest through interruption recovery and reconciliation', function (): void {
    $owner = User::factory()->create(['role' => 'owner', 'account_state' => 'approved']);

    $result = app(ArchiveMigrationQualification::class)->qualify($owner, 30000, 500, 12000);
    $run = MigrationQualificationRun::query()->where('qualification_id', $result['qualification_id'])->firstOrFail();
    $reconciliation = $run->qualification_profile['reconciliation'];

    expect($result)->toMatchArray([
        'target_count' => 30000,
        'checkpoint_count' => 60,
        'state' => 'qualified',
    ])->and($run->completed_count)->toBe(30000)
        ->and($run->restart_count)->toBe(1)
        ->and($run->isolated_failures)->toBe(3)
        ->and($run->recovered_failures)->toBe(3)
        ->and($run->duplicate_skips)->toBe(500)
        ->and($run->manifest_sha256)->toBe($run->reconciliation_sha256)
        ->and($reconciliation)->toMatchArray([
            'expected' => 30000,
            'observed' => 30000,
            'missing' => 0,
            'unexpected' => 0,
            'manifest_match' => true,
            'checkpoint_match' => true,
        ])->and($run->qualification_profile['source_media_retained'])->toBeFalse()
        ->and($run->qualification_profile['source_paths_persisted'])->toBeFalse()
        ->and($run->qualification_profile['real_private_migration_required'])->toBeTrue()
        ->and(DB::table('migration_qualification_items')->where('migration_qualification_run_id', $run->id)->count())->toBe(30000);
});

it('persists an interruption before resuming only pending entries', function (): void {
    $operator = User::factory()->create(['role' => 'trusted_contributor', 'account_state' => 'approved']);
    $qualification = app(ArchiveMigrationQualification::class);
    $run = $qualification->plan($operator, 1000, 250);

    $interrupted = $qualification->process($run->qualification_id, 2, [311]);
    expect($interrupted->state)->toBe('interrupted')
        ->and($interrupted->completed_count)->toBe(500)
        ->and($interrupted->checkpoint_count)->toBe(2);

    $resumed = $qualification->process($run->qualification_id, null, [311]);
    expect($resumed->state)->toBe('reconciling')
        ->and($resumed->completed_count)->toBe(1000)
        ->and($resumed->checkpoint_count)->toBe(4)
        ->and($resumed->restart_count)->toBe(1);

    $qualification->recover($run->qualification_id);
    $qualification->proveReplaySafety($run->qualification_id, 250);
    expect($qualification->reconcile($run->qualification_id)->state)->toBe('qualified');
});

it('requires trusted intake authority and protects the qualification screen', function (): void {
    $viewer = User::factory()->create(['role' => 'viewer', 'account_state' => 'approved']);
    $owner = User::factory()->create(['role' => 'owner', 'account_state' => 'approved']);

    expect(fn () => app(ArchiveMigrationQualification::class)->plan($viewer, 1000, 250))
        ->toThrow(RuntimeException::class, 'trusted intake operator');

    actingAs($viewer);
    get(route('admin.migration-qualification'))->assertForbidden();

    actingAs($owner);
    get(route('admin.migration-qualification'))->assertOk();
});
