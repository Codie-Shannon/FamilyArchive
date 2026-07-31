<?php

use App\Http\Middleware\PreventDemoWrites;
use App\Models\User;
use Database\Seeders\PortfolioDemoSeeder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

it('positions preservation as core and generic social expansion as deferred', function () {
    expect(config('portfolio_demo.positioning.core'))->toContain('Preservation integrity and provenance')
        ->and(config('portfolio_demo.positioning.deferred'))->toContain('Large public community servers')
        ->and(config('portfolio_demo.positioning.deferred'))->toContain('WhatsApp and Messenger publishing bridges')
        ->and(config('portfolio_demo.dataset'))->toBe('fictional-aotearoa-family');
});

it('refuses to seed the portfolio dataset outside an explicitly enabled demo environment', function () {
    $this->seed(PortfolioDemoSeeder::class);
})->throws(RuntimeException::class, 'restricted to local or explicitly enabled production demo environments');

it('permits the fictional dataset in an explicitly enabled production demo environment', function () {
    app()->detectEnvironment(fn (): string => 'production');
    config()->set('portfolio_demo.enabled', true);
    config()->set('portfolio_demo.password', 'production-demo-test-password');

    $this->artisan('db:seed', [
        '--class' => PortfolioDemoSeeder::class,
        '--force' => true,
    ])->assertSuccessful();

    $owner = User::query()->where('email', 'archive-owner@example.test')->firstOrFail();

    expect(Hash::check('production-demo-test-password', $owner->password))->toBeTrue();
});

it('makes authenticated product writes read only in portfolio mode', function () {
    config()->set('portfolio_demo.enabled', true);
    $owner = User::factory()->create(['role' => 'owner', 'email_verified_at' => now()]);

    $this->actingAs($owner)
        ->post(route('public-chat.message'), ['body' => 'fictional'])
        ->assertForbidden();
});

it('blocks every configured write method while leaving reads available', function () {
    config()->set('portfolio_demo.enabled', true);
    $middleware = app(PreventDemoWrites::class);
    $next = fn (): Response => new Response('continued', 200);

    foreach (['POST', 'PUT', 'PATCH', 'DELETE'] as $method) {
        expect(fn () => $middleware->handle(Request::create('/fictional-write', $method), $next))
            ->toThrow(HttpException::class);
    }

    $response = $middleware->handle(Request::create('/fictional-read', 'GET'), $next);
    expect($response->getContent())->toBe('continued');
});

it('leaves normal authenticated writes available when portfolio mode is off', function () {
    config()->set('portfolio_demo.enabled', false);
    $response = app(PreventDemoWrites::class)->handle(
        Request::create('/fictional-write', 'POST'),
        fn (): Response => new Response('continued', 200),
    );

    expect($response->getContent())->toBe('continued');
});

it('keeps the portfolio narrative inside the verified owner boundary', function () {
    $this->withoutVite();
    $owner = User::factory()->create(['role' => 'owner', 'email_verified_at' => now()]);
    $viewer = User::factory()->create(['role' => 'viewer', 'email_verified_at' => now()]);

    $this->get(route('admin.portfolio-showcase'))->assertRedirect('/login');
    $this->actingAs($viewer)->get(route('admin.portfolio-showcase'))->assertForbidden();
    $this->actingAs($owner)->get(route('admin.portfolio-showcase'))
        ->assertOk()
        ->assertSee('Preservation engineering, made demonstrable')
        ->assertSee('Protect the evidence. Preserve the story.')
        ->assertSee('De-emphasized expansion')
        ->assertSee('no real family data', false)
        ->assertDontSee('AWS_SECRET_ACCESS_KEY')
        ->assertDontSee('WASABI_SECRET_ACCESS_KEY');
});

it('renders each focused portfolio evidence view', function (string $view, string $proof): void {
    $this->withoutVite();
    $owner = User::factory()->create(['role' => 'owner', 'email_verified_at' => now()]);

    $this->actingAs($owner)
        ->get(route('admin.portfolio-showcase', ['view' => $view]))
        ->assertOk()
        ->assertSee($proof)
        ->assertSee('Fictional Aotearoa dataset')
        ->assertDontSee('demo/archive/');
})->with([
    ['journey', 'One coherent archive workflow'],
    ['integrity', 'Immutable lineage'],
    ['privacy', 'Reduced precision only'],
    ['architecture', 'System boundaries and storage flow'],
    ['accessibility', 'The same protected workflow at every breakpoint'],
]);

it('falls back to the product promise for an unknown view', function () {
    $this->withoutVite();
    $owner = User::factory()->create(['role' => 'owner', 'email_verified_at' => now()]);

    $this->actingAs($owner)
        ->get(route('admin.portfolio-showcase', ['view' => 'not-a-view']))
        ->assertOk()
        ->assertSee('Protect the evidence. Preserve the story.');
});

it('keeps release metadata aligned beyond the v1.6 portfolio release', function () {
    expect(version_compare((string) config('release.version'), '1.6.0', '>='))->toBeTrue()
        ->and(config('release.name'))->toBeString()->not->toBeEmpty()
        ->and(config('release.groups'))->toBeString()->not->toBeEmpty()
        ->and(config('release.status'))->toBeString()->not->toBeEmpty();
});
