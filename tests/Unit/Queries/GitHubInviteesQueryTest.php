<?php

declare(strict_types=1);

use App\Models\User;
use App\Queries\GitHubInviteesQuery;
use Illuminate\Support\Facades\Http;

it('drops someone from the cached list as soon as they join', function (): void {
    $user = User::factory()->create();

    Http::fake([
        "api.github.com/user/{$user->github_id}" => Http::response(['login' => $user->username]),
        "api.github.com/users/{$user->username}/following*" => Http::response([
            ['id' => 424242, 'login' => 'octocat', 'avatar_url' => 'https://avatars.githubusercontent.com/u/424242?v=4'],
        ]),
    ]);

    expect(resolve(GitHubInviteesQuery::class)->get($user))->toHaveCount(1);

    User::factory()->create(['github_id' => '424242']);

    expect(resolve(GitHubInviteesQuery::class)->get($user))->toBe([]);
    Http::assertSentCount(2);
});

it('does not cache a failed read', function (): void {
    $user = User::factory()->create();

    Http::fakeSequence("api.github.com/user/{$user->github_id}")
        ->pushStatus(502)
        ->push(['login' => $user->username]);
    Http::fake(["api.github.com/users/{$user->username}/following*" => Http::response([])]);

    expect(resolve(GitHubInviteesQuery::class)->get($user))->toBeNull()
        ->and(resolve(GitHubInviteesQuery::class)->get($user))->toBe([]);
});

it('lists nobody for a github account that no longer exists', function (): void {
    $user = User::factory()->create();

    Http::fake(["api.github.com/user/{$user->github_id}" => Http::response(status: 404)]);

    expect(resolve(GitHubInviteesQuery::class)->get($user))->toBe([]);
});
