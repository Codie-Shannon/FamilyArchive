<?php

use App\Domain\Knowledge\Enums\LocationPrecision;
use App\Domain\Knowledge\Models\ArchiveEvent;
use App\Domain\Knowledge\Models\ArchiveLocation;
use App\Domain\Knowledge\Models\ArchivePerson;
use App\Domain\Knowledge\Models\FamilyBranch;
use App\Models\User;

function sg23User(string $role = 'viewer'): User
{
    return User::factory()->create([
        'role' => $role,
        'account_state' => 'approved',
        'email_verified_at' => now(),
    ]);
}

it('gives approved members one safe archive journey', function (): void {
    $member = sg23User();

    foreach (['archive.index', 'archive.locations.index', 'archive.people.index', 'archive.events.index', 'archive.branches.index', 'archive.knowledge'] as $routeName) {
        $this->actingAs($member)
            ->get(route($routeName))
            ->assertOk()
            ->assertSee('Explore archive')
            ->assertSee('Photos')
            ->assertSee('Places &amp; map', false)
            ->assertSee('People')
            ->assertSee('Events')
            ->assertSee('Branches')
            ->assertSee('Search');
    }
});

it('filters private people sensitive branches and precise locations from members', function (): void {
    $member = sg23User();
    $safeBranch = FamilyBranch::factory()->create(['name' => 'Safe Harbour Branch']);
    $sensitiveBranch = FamilyBranch::factory()->create(['name' => 'Protected Branch', 'is_sensitive' => true]);
    $safePerson = ArchivePerson::factory()->create(['display_name' => 'Reviewed Ancestor', 'family_branch_id' => $safeBranch]);
    $privatePerson = ArchivePerson::factory()->create(['display_name' => 'Private Relative', 'is_private' => true, 'family_branch_id' => $safeBranch]);
    $livingPerson = ArchivePerson::factory()->create(['display_name' => 'Living Relative', 'life_state' => 'living', 'family_branch_id' => $safeBranch]);
    $safeLocation = ArchiveLocation::factory()->create(['label' => 'Reviewed Harbour']);
    $sensitiveLocation = ArchiveLocation::factory()->create(['label' => 'Protected Home', 'is_sensitive' => true, 'precision' => LocationPrecision::Private]);

    $this->actingAs($member)->get(route('archive.people.index'))
        ->assertOk()
        ->assertSee($safePerson->display_name)
        ->assertDontSee($privatePerson->display_name)
        ->assertDontSee($livingPerson->display_name);

    $this->actingAs($member)->get(route('archive.branches.index'))
        ->assertOk()
        ->assertSee($safeBranch->name)
        ->assertDontSee($sensitiveBranch->name);

    $this->actingAs($member)->get(route('archive.branches.show', $safeBranch))
        ->assertOk()
        ->assertSee($safeBranch->name)
        ->assertSee($safePerson->display_name)
        ->assertDontSee($privatePerson->display_name)
        ->assertDontSee($livingPerson->display_name);

    $this->actingAs($member)->get(route('archive.locations.index'))
        ->assertOk()
        ->assertSee($safeLocation->label)
        ->assertDontSee($sensitiveLocation->label);

    $this->actingAs($member)->get(route('archive.people.show', $privatePerson))->assertNotFound();
    $this->actingAs($member)->get(route('archive.branches.show', $sensitiveBranch))->assertNotFound();
    $this->actingAs($member)->get(route('archive.locations.show', $sensitiveLocation))->assertNotFound();
});

it('hides events that would reveal protected locations', function (): void {
    $member = sg23User();
    $safeLocation = ArchiveLocation::factory()->create(['label' => 'Safe Regional Place']);
    $sensitiveLocation = ArchiveLocation::factory()->create(['label' => 'Exact Private Address', 'is_sensitive' => true]);
    $safeEvent = ArchiveEvent::factory()->create(['name' => 'Reviewed Reunion', 'archive_location_id' => $safeLocation]);
    $protectedEvent = ArchiveEvent::factory()->create(['name' => 'Private Home Gathering', 'archive_location_id' => $sensitiveLocation]);

    $this->actingAs($member)->get(route('archive.events.index'))
        ->assertOk()
        ->assertSee($safeEvent->name)
        ->assertDontSee($protectedEvent->name);

    $this->actingAs($member)->get(route('archive.events.show', $protectedEvent))->assertNotFound();
});

it('keeps curation and provenance controls out of ordinary member views', function (): void {
    $member = sg23User();
    $person = ArchivePerson::factory()->create(['display_name' => 'Browse Only Ancestor']);

    $this->actingAs($member)->get(route('archive.people.show', $person))
        ->assertOk()
        ->assertSee('accepted family knowledge')
        ->assertDontSee('Review person')
        ->assertDontSee('Attach source')
        ->assertDontSee('Immutable revision evidence');

    $this->actingAs($member)->get(route('archive.people.create'))->assertForbidden();
});

it('retains complete reviewed knowledge and curation controls for the owner', function (): void {
    $owner = sg23User('owner');
    $sensitiveBranch = FamilyBranch::factory()->create(['name' => 'Owner Protected Branch', 'is_sensitive' => true]);
    $privatePerson = ArchivePerson::factory()->create(['display_name' => 'Owner Private Relative', 'is_private' => true, 'family_branch_id' => $sensitiveBranch]);

    $this->actingAs($owner)->get(route('archive.people.index'))
        ->assertOk()
        ->assertSee('Restricted person record')
        ->assertDontSee($privatePerson->display_name)
        ->assertSee('Add reviewed person');

    $this->actingAs($owner)->get(route('archive.people.show', $privatePerson))
        ->assertOk()
        ->assertSee('Review person')
        ->assertSee('Immutable revision evidence');
});

it('applies the same privacy filter to archive search', function (): void {
    $member = sg23User();
    $branch = FamilyBranch::factory()->create(['name' => 'Searchable Branch']);
    ArchivePerson::factory()->create(['display_name' => 'Searchable Ancestor', 'family_branch_id' => $branch]);
    ArchivePerson::factory()->create(['display_name' => 'Searchable Living Person', 'life_state' => 'living', 'family_branch_id' => $branch]);

    $this->actingAs($member)->get(route('archive.knowledge', ['q' => 'Searchable']))
        ->assertOk()
        ->assertSee('Searchable Ancestor')
        ->assertSee('Searchable Branch')
        ->assertDontSee('Searchable Living Person');
});
