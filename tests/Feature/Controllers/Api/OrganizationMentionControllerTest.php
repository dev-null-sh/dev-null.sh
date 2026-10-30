<?php

declare(strict_types=1);

use App\Actions\CreateCommitTopics;
use App\Models\Commit;
use App\Models\Organization;
use App\Models\User;

it('lists the public commits and replies that mention an organization, newest first', function (): void {
    $laravel = Organization::factory()->create(['handle' => 'laravel']);
    $symfony = Organization::factory()->create(['handle' => 'symfony']);
    $mention = fn (Commit $commit, ?Organization $organization = null): Commit => tap($commit, fn () => resolve(CreateCommitTopics::class)->handle($commit, [($organization ?? $laravel)->handle => ($organization ?? $laravel)->id]));

    $older = $mention(Commit::factory()->create(['body' => 'Thanks @laravel', 'created_at' => now()->subHour()]));
    $parent = Commit::factory()->create(['created_at' => now()->subMinutes(30)]);
    $reply = $mention(Commit::factory()->for($parent, 'parent')->create(['body' => '@laravel of course']));
    $mention(Commit::factory()->pending()->create(['body' => 'Pending @laravel']));
    $mention(Commit::factory()->create(['body' => 'Deleted @laravel']))->delete();
    $mention(Commit::factory()->create(['body' => 'Thanks @symfony']), $symfony);

    $this->getJson('/api/v1/organizations/laravel/mentions')
        ->assertOk()
        ->assertJsonPath('data.*.id', [$reply->id, $older->id])
        ->assertJsonPath('data.0.parent_id', $parent->id)
        ->assertJsonPath('meta.next_cursor', null);
});

it('lists for a signed request the mentions of an organization as the web shows them to its user', function (): void {
    $viewer = User::factory()->create();
    $keyPair = apiKeyPairFor($viewer);
    $muted = User::factory()->create();
    $viewer->muting()->attach($muted);
    $laravel = Organization::factory()->create(['handle' => 'laravel']);
    $mention = fn (Commit $commit): Commit => tap($commit, fn () => resolve(CreateCommitTopics::class)->handle($commit, ['laravel' => $laravel->id]));

    $pending = $mention(Commit::factory()->for($viewer)->pending()->create(['body' => 'Pending @laravel']));
    $parent = Commit::factory()->create(['created_at' => now()->subHour()]);
    $reply = $mention(Commit::factory()->for($parent, 'parent')->create(['body' => '@laravel', 'created_at' => now()->subMinute()]));
    $mention(Commit::factory()->for($muted)->create(['body' => 'Muted @laravel']));

    signedApiRequest($keyPair, 'GET', route('api-organization-mention.index', 'laravel'))
        ->assertOk()
        ->assertJsonPath('data.*.id', [$pending->id, $reply->id])
        ->assertJsonPath('data.0.status', 'pending')
        ->assertJsonPath('data.1.parent.id', $parent->id)
        ->assertJsonPath('data.1.parent.author.id', $parent->user_id);
});

it('does not find the mentions of a handle that is no organization', function (): void {
    User::factory()->create(['handle' => 'octocat']);

    $this->getJson('/api/v1/organizations/octocat/mentions')->assertNotFound();
});

it('lists the mentions of an organization whatever the handle\'s case', function (): void {
    $laravel = Organization::factory()->create(['handle' => 'Laravel']);
    $commit = Commit::factory()->create(['body' => 'Thanks @laravel']);
    resolve(CreateCommitTopics::class)->handle($commit, ['Laravel' => $laravel->id]);

    $this->getJson('/api/v1/organizations/laravel/mentions')
        ->assertOk()
        ->assertJsonPath('data.*.id', [$commit->id]);
});

it('lists the mentions of the organization in the exact case when a stale one holds the handle in another case', function (): void {
    $lower = Organization::factory()->create(['handle' => 'laravel']);
    $mixed = Organization::factory()->create(['handle' => 'Laravel']);
    $lowerCommit = Commit::factory()->create(['body' => 'Thanks @laravel']);
    $mixedCommit = Commit::factory()->create(['body' => 'Thanks @Laravel']);
    resolve(CreateCommitTopics::class)->handle($lowerCommit, ['laravel' => $lower->id]);
    resolve(CreateCommitTopics::class)->handle($mixedCommit, ['Laravel' => $mixed->id]);

    $this->getJson('/api/v1/organizations/laravel/mentions')->assertOk()->assertJsonPath('data.*.id', [$lowerCommit->id]);
    $this->getJson('/api/v1/organizations/Laravel/mentions')->assertOk()->assertJsonPath('data.*.id', [$mixedCommit->id]);
});
