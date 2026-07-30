<?php

use App\Domain\Communication\Services\FamilyCommunication;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

it('creates the family access and communication schema', function () {
    foreach ([
        'original_access_grants',
        'contributor_submissions',
        'upload_templates',
        'upload_sessions',
        'archive_stories',
        'conversation_threads',
        'conversation_messages',
        'anonymous_messages',
        'metadata_suggestions',
    ] as $table) {
        expect(Schema::hasTable($table))->toBeTrue();
    }
});

it('enforces approved membership for conversation posts', function () {
    $owner = User::factory()->create(['role' => 'owner', 'account_state' => 'approved']);
    $pending = User::factory()->create(['role' => 'viewer', 'account_state' => 'pending']);
    $thread = DB::table('conversation_threads')->insertGetId([
        'thread_id' => fake()->uuid(),
        'subject' => 'Fictional family question',
        'scope' => 'public',
        'created_by' => $owner->id,
        'is_locked' => false,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    expect(fn () => app(FamilyCommunication::class)->post($pending, $thread, 'Hello family'))
        ->toThrow(ValidationException::class);
    expect(app(FamilyCommunication::class)->post($owner, $thread, 'Hello family'))->toBeInt();
});

it('rejects posts to locked conversations', function () {
    $owner = User::factory()->create(['role' => 'owner', 'account_state' => 'approved']);
    $thread = DB::table('conversation_threads')->insertGetId([
        'thread_id' => fake()->uuid(),
        'subject' => 'Locked fictional question',
        'scope' => 'public',
        'created_by' => $owner->id,
        'is_locked' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    expect(fn () => app(FamilyCommunication::class)->post($owner, $thread, 'Hello family'))
        ->toThrow(ValidationException::class);
});

it('accepts anonymous messages into moderation without creating users', function () {
    $before = User::count();
    $id = app(FamilyCommunication::class)->acceptAnonymous(
        'A family history question',
        'This is a fictional question for the archive custodians.',
        null,
        'test-network-token',
    );

    expect($id)->toBeString()
        ->and(User::count())->toBe($before)
        ->and(DB::table('anonymous_messages')->where('message_id', $id)->value('moderation_state'))->toBe('pending');
});

it('publishes moderated conversation without granting archive access', function () {
    $this->withoutVite();

    $owner = User::factory()->create(['role' => 'owner', 'account_state' => 'approved']);
    DB::table('conversation_threads')->insert([
        'thread_id' => fake()->uuid(),
        'subject' => 'Fictional public family question',
        'scope' => 'public',
        'created_by' => $owner->id,
        'is_locked' => false,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->get(route('public-chat.index'))
        ->assertOk()
        ->assertSee('Family conversations')
        ->assertSee('never reveal private archive records');

    $this->post(route('anonymous-message.store'), [
        'subject' => 'A fictional enquiry',
        'body' => 'This is long enough to enter the moderation queue.',
    ])->assertSessionHas('status', 'Anonymous message entered moderation. No archive access was granted.');

    $this->get('/archive')->assertRedirect('/login');
});

it('keeps release metadata aligned', function () {
    expect(config('release.version'))->toBe('0.28.0')
        ->and(config('release.name'))->toBe('Family Access & Conversation')
        ->and(config('release.groups'))->toBe('21-28')
        ->and(config('release.status'))->toBe('Screenshot Group 02 implemented — evidence pending');
});
