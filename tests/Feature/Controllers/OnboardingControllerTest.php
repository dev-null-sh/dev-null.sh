<?php

declare(strict_types=1);

use App\Actions\CreateTomFriendship;
use App\Enums\Log;
use App\Http\Middleware\HandleInertiaRequests;
use App\Models\Commit;
use App\Models\User;
use Illuminate\Support\Facades\DB;

it('renders the onboarding for a new user', function (): void {
    $user = User::factory()->notOnboarded()->create([
        'id' => '3a788e93-4cf7-4819-9377-94be2f329563',
        'preferred_lane' => 1,
    ]);

    $response = $this->actingAs($user)
        ->get(route('onboarding.show'));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('onboarding/show')
            ->where('preferredLane', 1)
            ->where('automaticLane', 4)
            ->where('commitScriptUrl', route('cli-script.show'))
            ->where('suggestions', []));
});

it('leaves who to follow to the pages with a sidebar', function (): void {
    $user = User::factory()->notOnboarded()->create();

    $response = $this->actingAs($user)
        ->get(route('onboarding.show'));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page->where('showsWhoToFollow', true)->missing('suggestedUsers'));

    expect(collect($response->viewData('page')['deferredProps'] ?? [])->flatten())->not->toContain('suggestedUsers');
});

it('suggests developers to follow', function (): void {
    $user = User::factory()->notOnboarded()->create();
    $authors = User::factory()->count(6)->has(Commit::factory())->create();

    $response = $this->actingAs($user)
        ->get(route('onboarding.show'));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('suggestions', 5)
            ->where('suggestions', fn ($suggestions): bool => collect($suggestions)
                ->pluck('id')
                ->diff($authors->modelKeys())
                ->isEmpty()));
});

it('leaves the suggestions out of partial reloads that do not ask for them', function (): void {
    $user = User::factory()->notOnboarded()->create(['preferred_lane' => 1]);
    User::factory()->count(6)->has(Commit::factory())->create();

    DB::enableQueryLog();

    $this->actingAs($user)->withHeaders([
        'X-Inertia' => 'true',
        'X-Inertia-Version' => (string) new HandleInertiaRequests()->version(request()),
        'X-Inertia-Partial-Component' => 'onboarding/show',
        'X-Inertia-Partial-Data' => 'preferredLane',
    ])->get(route('onboarding.show'))->assertOk()
        ->assertJsonPath('props.preferredLane', 1)
        ->assertJsonMissingPath('props.suggestions');

    expect(collect(DB::getQueryLog())->filter(fn (array $query): bool => str_contains($query['query'], 'as "followed_by_following_count"')))->toBeEmpty();
});

it('waits for the GitHub import to have run', function (): void {
    $user = User::factory()->notOnboarded()->create(['github_follows_imported_at' => null]);

    $response = $this->actingAs($user)
        ->get(route('onboarding.show'));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page->where('githubImport', null));
});

it('shows the follows the GitHub import brought over', function (): void {
    $this->travelTo(now()->startOfSecond());

    $user = User::factory()->notOnboarded()->create(['github_follows_imported_at' => now()]);
    // Tom's friendship is made at sign-up too, right before the import...
    resolve(CreateTomFriendship::class)->handle($user);
    $followed = User::factory()->count(9)->create();
    $followers = User::factory()->count(2)->create();
    $user->following()->attach($followed->modelKeys(), ['created_at' => now()->subSeconds(3), 'updated_at' => now()]);
    $user->followers()->attach($followers->modelKeys(), ['created_at' => now()->subSeconds(3), 'updated_at' => now()]);

    // Followed after the import, from the onboarding itself...
    $this->travel(1)->minute();
    $user->following()->attach(User::factory()->create(), ['created_at' => now(), 'updated_at' => now()]);

    $response = $this->actingAs($user)
        ->get(route('onboarding.show'));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('githubImport.following', 5)
            ->where('githubImport.following', fn ($users): bool => collect($users)->pluck('id')->diff($followed->modelKeys())->isEmpty())
            ->where('githubImport.followingCount', 9)
            ->where('githubImport.followers', fn ($users): bool => collect($users)->pluck('id')->sort()->values()->all() === collect($followers->modelKeys())->sort()->values()->all())
            ->where('githubImport.followersCount', 2)
            ->where('githubImport.pendingCount', 0));
});

it('is not found once the user has onboarded', function (): void {
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->get(route('onboarding.show'));

    $response->assertNotFound();
});

it('completes the onboarding and lands on the log', function (): void {
    $user = User::factory()->notOnboarded()->create();

    $response = $this->actingAs($user)
        ->put(route('onboarding.update'));

    $response->assertRedirectToRoute('log.show', Log::Following);

    expect($user->refresh()->isOnboarded())->toBeTrue();

    $this->get(route('onboarding.show'))->assertNotFound();
});

it('lands on the log the user visited last', function (): void {
    $user = User::factory()->notOnboarded()->create(['log' => Log::Trending]);

    $response = $this->actingAs($user)
        ->put(route('onboarding.update'));

    $response->assertRedirectToRoute('log.show', Log::Trending);
});

it('lands on the page the user was headed to before signing in', function (): void {
    $user = User::factory()->notOnboarded()->create();
    $author = User::factory()->create();

    $response = $this->actingAs($user)
        ->withSession(['url.intended' => route('user.show', $author)])
        ->put(route('onboarding.update'));

    $response->assertRedirectToRoute('user.show', $author);
});

it('requires authentication', function (): void {
    $this->get(route('onboarding.show'))->assertRedirectToRoute('home');
    $this->put(route('onboarding.update'))->assertRedirectToRoute('home');
});
