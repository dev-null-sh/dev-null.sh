<?php

declare(strict_types=1);

use App\Actions\CreateUserGitHubFollow;
use App\Models\User;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

it('follows the account under its current login, with the given token', function (): void {
    $user = User::factory()->create(['username' => 'old-login']);

    Http::fake([
        "api.github.com/user/{$user->github_id}" => Http::response(['login' => 'new-login']),
        'api.github.com/user/following/new-login' => Http::response(status: 204),
    ]);

    expect(resolve(CreateUserGitHubFollow::class)->handle('gho_follow', $user))->toBeTrue();

    Http::assertSent(fn (Request $request): bool => $request->method() === 'PUT'
        && $request->url() === 'https://api.github.com/user/following/new-login'
        && $request->hasHeader('Authorization', 'Bearer gho_follow'));
});

it('follows nobody when the account is gone from github', function (): void {
    $user = User::factory()->create();

    Http::fake([
        "api.github.com/user/{$user->github_id}" => Http::response(status: 404),
    ]);

    expect(resolve(CreateUserGitHubFollow::class)->handle('gho_follow', $user))->toBeFalse();

    Http::assertSentCount(1);
});

it('fails when github does not follow', function (): void {
    $user = User::factory()->create();

    Http::fake([
        "api.github.com/user/{$user->github_id}" => Http::response(['login' => $user->username]),
        'api.github.com/user/following/*' => Http::response(status: 422),
    ]);

    expect(fn () => resolve(CreateUserGitHubFollow::class)->handle('gho_follow', $user))
        ->toThrow(RequestException::class);
});
