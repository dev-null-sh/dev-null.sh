<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Http;

it('lists the merged pull requests for anyone, with a link to every commit on production', function (?bool $isSignedIn): void {
    Http::fake(['api.github.com/*' => Http::response([[
        'number' => 377,
        'title' => 'feat: turn emoji shortcodes into emoji in remotes',
        'html_url' => 'https://github.com/nunomaduro/dev-null.sh/pull/377',
        'merged_at' => '2026-10-18T00:53:44Z',
        'merge_commit_sha' => 'c897f86700000000000000000000000000000000',
        'user' => ['login' => 'xHeaven', 'avatar_url' => 'https://avatars.githubusercontent.com/u/1?v=4'],
    ]])]);

    $request = $isSignedIn ? $this->actingAs(User::factory()->create()) : $this;

    $request->getJson('/changelog')
        ->assertOk()
        ->assertJsonPath('commitsUrl', 'https://github.com/nunomaduro/dev-null.sh/commits/production')
        ->assertJsonPath('pullRequests.0.number', 377)
        ->assertJsonPath('pullRequests.0.type', 'feat')
        ->assertJsonPath('pullRequests.0.title', 'Turn emoji shortcodes into emoji in remotes')
        ->assertJsonPath('pullRequests.0.author.login', 'xHeaven');
})->with(['guest' => false, 'signed in' => true]);

it('says the changelog is unavailable when GitHub cannot be reached', function (): void {
    Http::fake(['api.github.com/*' => Http::failedConnection()]);

    $this->getJson('/changelog')
        ->assertOk()
        ->assertJsonPath('pullRequests', null);
});
