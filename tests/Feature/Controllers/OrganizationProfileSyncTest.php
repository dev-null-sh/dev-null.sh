<?php

declare(strict_types=1);

use App\Contracts\Services\GitHubProvider;
use App\Enums\OrganizationRole;
use App\Models\Organization;
use App\Models\User;
use GuzzleHttp\Psr7\Response as Psr7Response;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;

it('lets an organization admin sync its GitHub profile by immutable ID', function (): void {
    $admin = User::factory()->create();
    $organization = Organization::factory()->create([
        'github_id' => '958072',
        'handle' => 'old-laravel',
        'name' => 'Old name',
        'bio' => 'Old bio',
        'lane_color' => '#1a2b3c',
        'github_synced_at' => now()->subWeek(),
    ]);
    $organization->admins()->attach($admin->id);
    $other = Organization::factory()->create(['handle' => 'other-organization']);

    $this->mock(GitHubProvider::class)
        ->shouldReceive('account')
        ->once()
        ->with('958072')
        ->andReturn([
            'id' => 958072,
            'type' => 'Organization',
            'login' => 'laravel',
            'name' => 'Laravel',
            'avatar_url' => 'https://avatars.githubusercontent.com/u/958072',
            'bio' => 'The PHP framework.',
            'location' => 'Little Rock',
            'blog' => 'laravel.com',
        ]);

    $this->actingAs($admin)->from(route('organization-profile.edit', $organization))
        ->put(route('organization-profile.update', $organization))
        ->assertRedirectToRoute('organization-profile.edit', $organization)
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Synced from GitHub.']);

    expect($organization->refresh())
        ->handle->toBe('laravel')
        ->name->toBe('Laravel')
        ->bio->toBe('The PHP framework.')
        ->location->toBe('Little Rock')
        ->website->toBe('https://laravel.com')
        ->avatar_url->toBe('https://avatars.githubusercontent.com/u/958072')
        ->github_synced_at->toDateTimeString()->toBe(now()->toDateTimeString())
        ->lane_color->toBe('#1a2b3c');

    expect($organization->isAdmin($admin))->toBeTrue()
        ->and($other->refresh()->handle)->toBe('other-organization');
});

it('only lets admins of that specific organization sync it', function (string $role): void {
    $admin = User::factory()->create();
    $organization = Organization::factory()->create(['bio' => 'Old bio']);
    $other = Organization::factory()->create();
    $user = match ($role) {
        'member' => User::factory()->create(),
        'nonmember' => User::factory()->create(),
        'other admin' => $admin,
    };

    $organization->admins()->attach(User::factory()->create()->id);
    if ($role === 'member') {
        $organization->members()->attach($user->id, ['role' => OrganizationRole::Member]);
    }
    if ($role === 'other admin') {
        $other->admins()->attach($user->id);
    }

    $this->mock(GitHubProvider::class)->shouldNotReceive('account');

    $this->actingAs($user)->put(route('organization-profile.update', $organization))
        ->assertNotFound();

    expect($organization->refresh()->bio)->toBe('Old bio');
})->with(['member', 'nonmember', 'other admin']);

it('requires sign-in to sync an organization', function (): void {
    $organization = Organization::factory()->create();

    $this->put(route('organization-profile.update', $organization))
        ->assertRedirectToRoute('home');
});

it('does not sync another GitHub account or a non-organization', function (array $account): void {
    $admin = User::factory()->create();
    $organization = Organization::factory()->create([
        'github_id' => '958072',
        'handle' => 'old-laravel',
        'bio' => 'Old bio',
        'github_synced_at' => now()->subWeek(),
    ]);
    $organization->admins()->attach($admin->id);

    $this->mock(GitHubProvider::class)
        ->shouldReceive('account')
        ->once()
        ->with('958072')
        ->andReturn($account);

    $this->actingAs($admin)->from(route('organization-profile.edit', $organization))
        ->put(route('organization-profile.update', $organization))
        ->assertInertiaFlash('toast', [
            'type' => 'error',
            'message' => 'Could not sync from GitHub. Try again in a minute.',
        ]);

    expect($organization->refresh())
        ->handle->toBe('old-laravel')
        ->bio->toBe('Old bio')
        ->github_synced_at->toDateTimeString()->toBe(now()->subWeek()->toDateTimeString());
})->with([
    'different account' => [['id' => 12, 'type' => 'Organization', 'login' => 'taken-over']],
    'GitHub user' => [['id' => 958072, 'type' => 'User', 'login' => 'some-user']],
    'missing type' => [['id' => 958072, 'login' => 'laravel']],
    'missing ID' => [['type' => 'Organization', 'login' => 'laravel']],
    'missing handle' => [['id' => 958072, 'type' => 'Organization']],
]);

it('leaves the profile alone when GitHub is unavailable', function (bool $missing): void {
    $admin = User::factory()->create();
    $organization = Organization::factory()->create([
        'github_id' => '958072',
        'handle' => 'laravel',
        'bio' => 'Old bio',
        'github_synced_at' => now()->subWeek(),
    ]);
    $organization->admins()->attach($admin->id);

    $github = $this->mock(GitHubProvider::class);
    $expectation = $github->shouldReceive('account')->once()->with('958072');

    $missing
        ? $expectation->andReturnNull()
        : $expectation->andThrow(new RequestException(new Response(new Psr7Response(503))));

    $this->actingAs($admin)->from(route('organization-profile.edit', $organization))
        ->put(route('organization-profile.update', $organization))
        ->assertRedirectToRoute('organization-profile.edit', $organization)
        ->assertInertiaFlash('toast', [
            'type' => 'error',
            'message' => 'Could not sync from GitHub. Try again in a minute.',
        ]);

    expect($organization->refresh())
        ->handle->toBe('laravel')
        ->bio->toBe('Old bio')
        ->github_synced_at->toDateTimeString()->toBe(now()->subWeek()->toDateTimeString());
})->with(['missing' => true, 'GitHub error' => false]);

it('does not allow a signed-in user to sync another organization through their own admin role', function (): void {
    $admin = User::factory()->create();
    $mine = Organization::factory()->create();
    $another = Organization::factory()->create();

    $mine->admins()->attach($admin->id);

    $this->mock(GitHubProvider::class)->shouldNotReceive('account');

    $this->actingAs($admin)
        ->put(route('organization-profile.update', $another))
        ->assertNotFound();
});
