<?php

declare(strict_types=1);

use App\Actions\CreateCommitTopics;
use App\Models\Commit;
use App\Models\Profile;
use App\Models\User;

it('lists the public commits and replies that mention an organization, newest first', function (): void {
    $laravel = Profile::factory()->organization()->create(['handle' => 'laravel']);
    $symfony = Profile::factory()->organization()->create(['handle' => 'symfony']);
    $mention = fn (Commit $commit, ?Profile $organization = null): Commit => tap($commit, fn () => resolve(CreateCommitTopics::class)->handle($commit, [($organization ?? $laravel)->handle => ($organization ?? $laravel)->id]));

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
    $laravel = Profile::factory()->organization()->create(['handle' => 'laravel']);
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
