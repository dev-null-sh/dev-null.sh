<?php

declare(strict_types=1);

use App\Contracts\Services\GitHubProvider;
use App\Models\Organization;
use App\Models\User;
use GuzzleHttp\Psr7\Response as Psr7Response;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

it('renders profile settings page', function (): void {
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->get(route('user-profile.edit'));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('user-profile/edit')
            ->where('auth.user.handle', $user->handle)
            ->where('auth.user.dev_null_username', null)
            ->where('auth.user.display_handle', $user->handle));
});

it('saves editable profile details and a normalized Dev-Null username', function (): void {
    $user = User::factory()->create(['handle' => 'octocat']);

    $response = $this->actingAs($user)
        ->fromRoute('user-profile.edit')
        ->patch(route('user-profile.details.update'), [
            'dev_null_username' => '  Octo-Cat  ',
            'name' => 'The Octocat',
            'bio' => 'Building things.',
            'company' => '@dev-null',
            'location' => 'The Internet',
            'website' => 'https://example.test',
            'sponsors_url' => 'https://github.com/sponsors/octocat',
        ]);

    $response->assertRedirectToRoute('user-profile.edit')
        ->assertInertiaFlash('toast', [
            'type' => 'success',
            'message' => 'Profile saved.',
        ]);

    expect($user->refresh())
        ->dev_null_username->toBe('octo-cat')
        ->name->toBe('The Octocat')
        ->bio->toBe('Building things.')
        ->company->toBe('@dev-null')
        ->location->toBe('The Internet')
        ->website->toBe('https://example.test')
        ->sponsors_url->toBe('https://github.com/sponsors/octocat');
});

it('allows a user to clear their Dev-Null username', function (): void {
    $user = User::factory()->create([
        'dev_null_username' => 'octo-cat',
        'sponsors_url' => 'https://example.test/sponsor',
    ]);

    $this->actingAs($user)
        ->fromRoute('user-profile.edit')
        ->patch(route('user-profile.details.update'), [
            'dev_null_username' => '',
            'name' => $user->name,
            'bio' => $user->bio,
            'company' => $user->company,
            'location' => $user->location,
            'website' => $user->website,
            'sponsors_url' => '',
        ])
        ->assertRedirectToRoute('user-profile.edit');

    expect($user->refresh())
        ->dev_null_username->toBeNull()
        ->sponsors_url->toBeNull();
});

it('rejects non-http profile links', function (): void {
    $user = User::factory()->create([
        'website' => 'https://example.test',
        'sponsors_url' => 'https://example.test/sponsor',
    ]);

    $this->actingAs($user)
        ->fromRoute('user-profile.edit')
        ->patch(route('user-profile.details.update'), [
            'dev_null_username' => $user->dev_null_username,
            'name' => $user->name,
            'bio' => $user->bio,
            'company' => $user->company,
            'location' => $user->location,
            'website' => 'javascript:alert(1)',
            'sponsors_url' => 'javascript:alert(1)',
        ])
        ->assertSessionHasErrors(['website', 'sponsors_url']);

    expect($user->refresh())
        ->website->toBe('https://example.test')
        ->sponsors_url->toBe('https://example.test/sponsor');
});

it('rejects duplicate and invalid Dev-Null usernames', function (string $username): void {
    $user = User::factory()->create(['dev_null_username' => 'taken']);
    $other = User::factory()->create();

    $response = $this->actingAs($other)
        ->fromRoute('user-profile.edit')
        ->patch(route('user-profile.details.update'), [
            'dev_null_username' => $username,
            'name' => $other->name,
            'bio' => $other->bio,
            'company' => $other->company,
            'location' => $other->location,
            'website' => $other->website,
            'sponsors_url' => $other->sponsors_url,
        ]);

    $response->assertSessionHasErrors('dev_null_username');
    expect($other->refresh()->dev_null_username)->toBeNull();
})->with(['TAKEN', 'not_a_valid_name']);

it('rejects a Dev-Null username that matches the GitHub username', function (): void {
    $user = User::factory()->create(['handle' => 'octocat']);

    $this->actingAs($user)
        ->fromRoute('user-profile.edit')
        ->patch(route('user-profile.details.update'), [
            'dev_null_username' => 'OCTOCAT',
            'name' => $user->name,
            'bio' => $user->bio,
            'company' => $user->company,
            'location' => $user->location,
            'website' => $user->website,
            'sponsors_url' => $user->sponsors_url,
        ])
        ->assertSessionHasErrors('dev_null_username');

    expect($user->refresh()->dev_null_username)->toBeNull();

    $this->getJson(route('user-profile.dev-null-username.check', ['dev_null_username' => 'OCTOCAT']))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('dev_null_username');
});

it('rejects a Dev-Null username that matches another account displayed handle', function (): void {
    User::factory()->create(['handle' => 'displayed-handle']);
    Organization::factory()->create(['handle' => 'displayed-org']);
    $user = User::factory()->create();

    foreach (['DISPLAYED-HANDLE', 'DISPLAYED-ORG'] as $username) {
        $this->actingAs($user)
            ->fromRoute('user-profile.edit')
            ->patch(route('user-profile.details.update'), [
                'dev_null_username' => $username,
                'name' => $user->name,
                'bio' => $user->bio,
                'company' => $user->company,
                'location' => $user->location,
                'website' => $user->website,
                'sponsors_url' => $user->sponsors_url,
            ])
            ->assertSessionHasErrors('dev_null_username');
    }

    expect($user->refresh()->dev_null_username)->toBeNull();

    $this->getJson(route('user-profile.dev-null-username.check', ['dev_null_username' => 'displayed-handle']))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('dev_null_username');
});

it('checks Dev-Null username availability while preserving the current username', function (): void {
    User::factory()->create(['dev_null_username' => 'taken']);
    $user = User::factory()->create(['dev_null_username' => 'current-name']);

    $this->actingAs($user)
        ->getJson(route('user-profile.dev-null-username.check', ['dev_null_username' => 'new-name']))
        ->assertOk()
        ->assertJson(['available' => true]);

    $this->getJson(route('user-profile.dev-null-username.check', ['dev_null_username' => 'current-name']))
        ->assertOk()
        ->assertJson(['available' => true]);

    $this->getJson(route('user-profile.dev-null-username.check', ['dev_null_username' => 'TAKEN']))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('dev_null_username');

    $this->getJson(route('user-profile.dev-null-username.check', ['dev_null_username' => 'not_a_valid_name']))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('dev_null_username');
});

it('requires authentication to save a Dev-Null username', function (): void {
    $response = $this->patch(route('user-profile.details.update'));

    $response->assertRedirectToRoute('home');
});

it('stores and serves an uploaded profile image', function (): void {
    Storage::fake();
    Storage::disk()->put('profile-avatars/old.webp', 'old image');
    $user = User::factory()->create(['avatar_upload_path' => 'profile-avatars/old.webp']);

    $this->actingAs($user)
        ->fromRoute('user-profile.edit')
        ->patch(route('user-profile.details.update'), [
            'dev_null_username' => $user->dev_null_username,
            'name' => $user->name,
            'bio' => $user->bio,
            'company' => $user->company,
            'location' => $user->location,
            'website' => $user->website,
            'sponsors_url' => $user->sponsors_url,
            'avatar' => UploadedFile::fake()->image('avatar.png'),
        ])
        ->assertRedirectToRoute('user-profile.edit');

    $user->refresh();

    $path = $user->avatar_upload_path;

    expect($path)->toStartWith('profile-avatars/')
        ->and($user->avatar_url)->toBe(route('user.avatar', [
            'user' => $user,
            'v' => hash('xxh3', $path),
        ]));
    Storage::disk()->assertExists($path);
    Storage::disk()->assertMissing('profile-avatars/old.webp');

    $this->get(route('user.avatar', ['user' => $user, 'v' => hash('xxh3', $path)]))
        ->assertOk()
        ->assertHeader('Content-Type', 'image/webp')
        ->assertHeader('Cache-Control', 'public, max-age=31536000, immutable');
    $this->get(route('user.avatar', ['user' => $user, 'v' => 'stale-version']))
        ->assertNotFound();
});

it('renders the chosen and automatic lanes', function (?int $preferredLane): void {
    $user = User::factory()->create(['id' => '3a788e93-4cf7-4819-9377-94be2f329563', 'preferred_lane' => $preferredLane]);

    $response = $this->actingAs($user)
        ->get(route('user-profile.edit'));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('preferredLane', $preferredLane)
            ->where('automaticLane', 4)
            ->where('auth.user.lane', $preferredLane ?? 4));
})->with([null, 1]);

it('renders the custom color as the chosen lane', function (): void {
    $user = User::factory()->create(['lane_color' => '#1a2b3c']);

    $response = $this->actingAs($user)
        ->get(route('user-profile.edit'));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('preferredLane', '#1a2b3c')
            ->where('auth.user.lane', '#1a2b3c'));
});

it('renders the activity graph links with the timezone preference', function (?string $timezone): void {
    $user = User::factory()->create(['handle' => 'octocat', 'timezone' => $timezone]);

    $response = $this->actingAs($user)
        ->get(route('user-profile.edit'));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('activityGraphUrl', url('/@octocat/graph.svg'))
            ->where('profileUrl', url('/@octocat'))
            ->where('auth.user.timezone', $timezone));
})->with([null, 'Europe/Budapest']);

it('redirects settings to profile settings', function (): void {
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->get('/settings');

    $response->assertRedirectToRoute('user-profile.edit');
});

it('renders appearance settings page', function (): void {
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->get(route('appearance.edit'));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('appearance/update'));
});

it('renders terminal settings page', function (): void {
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->get(route('terminal.show'));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('terminal/show')
            ->where('url', url('/')));
});

it('requires authentication', function (string $route): void {
    $response = $this->get(route($route));

    $response->assertRedirectToRoute('home');
})->with(['user-profile.edit', 'appearance.edit', 'terminal.show']);

it('renders when the profile was last synced from github', function (?string $syncedAt): void {
    $user = User::factory()->create(['github_synced_at' => $syncedAt]);

    $response = $this->actingAs($user)
        ->get(route('user-profile.edit'));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('githubSyncedAt', $syncedAt === null ? null : now()->parse($syncedAt)->toIso8601String()));
})->with([null, '2026-10-01 09:30:00']);

it('may sync the profile from github', function (): void {
    Storage::fake();
    Storage::disk()->put('profile-avatars/old.webp', 'old image');
    $user = User::factory()->create([
        'github_id' => '583231',
        'handle' => 'old-handle',
        'dev_null_username' => 'my-dev-null',
        'name' => 'Custom name',
        'bio' => 'Old bio',
        'company' => 'Custom company',
        'location' => 'Custom location',
        'website' => 'https://custom.example.test',
        'has_sponsors_listing' => true,
        'sponsors_url' => 'https://custom.example.test/sponsor',
        'avatar_upload_path' => 'profile-avatars/old.webp',
    ]);

    $github = $this->mock(GitHubProvider::class);
    $github->shouldReceive('account')->with($user->github_id)->andReturn([
        'login' => 'octocat',
        'name' => 'Synced name',
        'avatar_url' => 'https://avatars.githubusercontent.com/u/583231?v=4',
        'bio' => 'Synced bio',
        'company' => '@github',
        'location' => 'San Francisco',
        'blog' => 'github.blog',
    ]);
    $github->shouldReceive('login')->with($user->github_id)->andReturn('octocat');
    $github->shouldReceive('sshKeys')->with('octocat')->andReturn([]);

    $response = $this->actingAs($user)
        ->fromRoute('user-profile.edit')
        ->put(route('user-profile.update'));

    $response->assertRedirectToRoute('user-profile.edit')
        ->assertInertiaFlash('toast', [
            'type' => 'success',
            'message' => 'Synced from GitHub.',
        ]);

    expect($user->refresh())
        ->handle->toBe('octocat')
        ->dev_null_username->toBe('my-dev-null')
        ->name->toBe('Synced name')
        ->bio->toBe('Synced bio')
        ->company->toBe('@github')
        ->location->toBe('San Francisco')
        ->website->toBe('https://github.blog')
        ->sponsors_url->toBe('https://github.com/sponsors/octocat')
        ->avatar_upload_path->toBeNull()
        ->avatar_url->toBe('https://avatars.githubusercontent.com/u/583231?v=4')
        ->github_synced_at->toDateTimeString()->toBe(now()->toDateTimeString());
    Storage::disk()->assertMissing('profile-avatars/old.webp');
});

it('tells the user when github could not be synced', function (): void {
    $user = User::factory()->create(['handle' => 'old-handle']);

    $github = $this->mock(GitHubProvider::class);
    $github->shouldReceive('account')->with($user->github_id)->andThrow(new RequestException(new Response(new Psr7Response(500))));

    $response = $this->actingAs($user)
        ->fromRoute('user-profile.edit')
        ->put(route('user-profile.update'));

    $response->assertRedirectToRoute('user-profile.edit')
        ->assertInertiaFlash('toast', [
            'type' => 'error',
            'message' => 'Could not sync from GitHub. Try again in a minute.',
        ]);

    expect($user->refresh()->handle)->toBe('old-handle');
});

it('allows one sync a minute', function (): void {
    $user = User::factory()->create();
    $other = User::factory()->create();

    $github = $this->mock(GitHubProvider::class);
    $github->shouldReceive('account')->with($user->github_id)->andReturn(['login' => 'octocat']);
    $github->shouldReceive('login')->with($user->github_id)->andReturn('octocat');
    $github->shouldReceive('sshKeys')->with('octocat')->andReturn([]);

    $this->actingAs($user)
        ->fromRoute('user-profile.edit')
        ->put(route('user-profile.update'));

    $response = $this->actingAs($user)
        ->fromRoute('user-profile.edit')
        ->put(route('user-profile.update'));

    $response->assertRedirectToRoute('user-profile.edit')
        ->assertInertiaFlash('toast', [
            'type' => 'error',
            'message' => 'You just synced. Try again in a minute.',
        ]);

    $github->shouldHaveReceived('account')->once();

    $this->travel(1)->minute();

    $this->actingAs($user)
        ->fromRoute('user-profile.edit')
        ->put(route('user-profile.update'))
        ->assertInertiaFlash('toast.type', 'success');

    $github->shouldReceive('account')->with($other->github_id)->andReturn(['login' => 'hubot']);
    $github->shouldReceive('login')->with($other->github_id)->andReturn('hubot');
    $github->shouldReceive('sshKeys')->with('hubot')->andReturn([]);

    $this->actingAs($other)
        ->fromRoute('user-profile.edit')
        ->put(route('user-profile.update'))
        ->assertInertiaFlash('toast.type', 'success');
});

it('requires authentication to sync', function (): void {
    $github = $this->mock(GitHubProvider::class);

    $response = $this->put(route('user-profile.update'));

    $response->assertRedirectToRoute('home');

    $github->shouldNotHaveReceived('account');
});

it('shows the private translation preference and supported choices only in own settings', function (): void {
    $user = User::factory()->create(['translation_language' => 'ja']);
    $other = User::factory()->create(['translation_language' => 'de']);

    $this->actingAs($user)->get(route('user-profile.edit', ['user_id' => $other->id]))
        ->assertOk()->assertInertia(fn ($page) => $page
        ->where('translationLanguage', 'ja')
        ->where('translationLanguages', fn ($languages): bool => collect($languages)->contains(fn ($language): bool => $language['code'] === 'hu' && $language['name'] === 'Hungarian' && $language['nativeName'] === 'magyar'))
        ->missing('auth.user.translation_language'));
});
