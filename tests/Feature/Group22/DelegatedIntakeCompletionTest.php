<?php

use App\Domain\Access\Models\UploadSession;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

beforeEach(function (): void {
    Storage::fake('archive_quarantine');
    Storage::fake('archive_originals');
    Storage::fake('archive_derivatives');
    Storage::fake('archive_manifests');
    $this->withoutVite();
});

it('turns a trusted browser upload into a self-reviewable batch', function (): void {
    $trusted = User::factory()->create(['role' => 'trusted_contributor']);

    $this->actingAs($trusted)->post(route('contributor.sessions.start'), [
        'title' => 'Family album box one',
        'source_context' => 'Inherited album retained by the contributor.',
        'expected_files' => 2,
        'automation_preset' => 'balanced',
    ])->assertRedirect();

    $session = UploadSession::query()->firstOrFail();
    expect($session->cloud_import_session_id)->not->toBeNull()
        ->and($session->automation_preferences['crop_target'])->toBe('photo_edge')
        ->and($session->automation_preferences['auto_rotate'])->toBeTrue();

    $this->actingAs($trusted)->post(route('contributor.sessions.upload', $session), [
        'photos' => [
            UploadedFile::fake()->image('grandparents.jpg', 900, 650),
            UploadedFile::fake()->image('wedding.jpg', 800, 600),
        ],
    ])->assertRedirect();

    $session->refresh();
    expect($session->status)->toBe('complete')
        ->and($session->received_files)->toBe(2)
        ->and(DB::table('cloud_import_sessions')->value('state'))->toBe('complete')
        ->and(DB::table('cloud_import_sessions')->value('review_state'))->toBe('ready')
        ->and(DB::table('cloud_import_items')->count())->toBe(2);

    $this->actingAs($trusted)
        ->get(route('intake.batches.show', $session->session_id))
        ->assertOk()
        ->assertSeeText('Review batch');
});

it('lets a contributor finish early while delegating review to an administrator', function (): void {
    $contributor = User::factory()->create(['role' => 'contributor']);
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($contributor)->post(route('contributor.sessions.start'), [
        'title' => 'Loose prints',
        'source_context' => 'Loose prints from a labelled envelope.',
        'expected_files' => 10,
        'automation_preset' => 'conservative',
    ])->assertRedirect();
    $session = UploadSession::query()->firstOrFail();

    $this->actingAs($contributor)->post(route('contributor.sessions.upload', $session), [
        'photos' => [UploadedFile::fake()->image('print.jpg', 700, 500)],
    ])->assertRedirect();
    $this->actingAs($contributor)->post(route('contributor.sessions.finish', $session))->assertRedirect();

    $session->refresh();
    expect($session->status)->toBe('complete')
        ->and($session->expected_files)->toBe(1)
        ->and(DB::table('cloud_import_sessions')->value('review_state'))->toBe('ready');

    $this->actingAs($contributor)->get(route('intake.index'))->assertForbidden();
    $this->actingAs($admin)
        ->get(route('intake.batches.show', $session->session_id))
        ->assertOk()
        ->assertSeeText('Review batch');
});

it('shows a completed upload session safely after its review record is removed', function (): void {
    $trusted = User::factory()->create(['role' => 'trusted_contributor']);
    $session = UploadSession::query()->create([
        'session_id' => Str::uuid()->toString(),
        'user_id' => $trusted->id,
        'title' => 'Retained family photos',
        'source_context' => 'Previously retained source files.',
        'automation_preferences' => [],
        'expected_files' => 1,
        'received_files' => 1,
        'status' => 'complete',
        'expires_at' => now()->addDay(),
    ]);

    $this->actingAs($trusted)
        ->get(route('contributor.sessions.show', $session))
        ->assertOk()
        ->assertSeeText('Review record unavailable')
        ->assertDontSeeText('Review this batch');
});

it('posts routine family conversation messages without owner approval', function (): void {
    $member = User::factory()->create(['role' => 'viewer']);
    $threadId = DB::table('conversation_threads')->insertGetId([
        'thread_id' => fake()->uuid(),
        'scope' => 'public',
        'subject' => 'Family memories',
        'created_by' => $member->id,
        'is_locked' => false,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->actingAs($member)->post(route('public-chat.message'), [
        'thread_id' => $threadId,
        'body' => 'I remember this picnic too.',
    ])->assertRedirect()->assertSessionHas('status', 'Message posted to the family conversation.');

    expect(DB::table('conversation_messages')->value('moderation_state'))->toBe('visible');
});
