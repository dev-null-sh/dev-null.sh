<?php

declare(strict_types=1);

use App\Models\User;
use App\Queries\UserPullRequestsQuery;
use Database\Seeders\ProfilePullRequestsSeeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

it('gives the local developer a repeatable pull request preview without github', function (): void {
    $this->app['env'] = 'local';

    $this->seed(ProfilePullRequestsSeeder::class);
    $this->seed(ProfilePullRequestsSeeder::class);

    $user = User::query()->where('github_id', 'local')->sole();

    expect(User::query()->count())->toBe(1);

    $this->get(route('user.show', $user))->assertOk()
        ->assertInertia(fn ($page) => $page
            ->loadDeferredProps(fn ($reload) => $reload
                ->where('githubPullRequests', [
                    'count' => 42,
                    'url' => 'https://github.com/'.config()->string('services.github.repository').'/pulls',
                ])));

    Http::assertNothingSent();

    $this->travel(25)->hours();

    expect(resolve(UserPullRequestsQuery::class)->get($user))->toBeNull();
    Http::assertNothingSent();
});

it('never seeds pull request preview data outside local development', function (string $environment): void {
    $this->app['env'] = $environment;
    $user = User::factory()->create(['github_id' => 'local']);

    $this->seed(ProfilePullRequestsSeeder::class);

    expect(Cache::has('github-pull-requests:local'))->toBeFalse();

    $query = resolve(UserPullRequestsQuery::class);

    expect($query->get($user))->toBeNull();
    Http::assertNothingSent();
})->with(['testing', 'production']);
