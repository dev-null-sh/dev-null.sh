<?php

declare(strict_types=1);

use App\Models\Organization;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Support\Facades\DB;

it('shows an unclaimed developer to anyone, but never where they are, with how many members wait to follow them', function (): void {
    $profile = Profile::factory()->create([
        'handle' => 'OctoCat',
        'name' => 'The Octocat',
        'avatar_url' => 'https://avatars.githubusercontent.com/u/583231',
        'bio' => 'I am the Octocat.',
        'location' => 'San Francisco',
        'website' => 'https://github.blog',
    ]);
    DB::table('pending_follows')->insert(['user_id' => User::factory()->create()->id, 'github_id' => $profile->github_id, 'created_at' => now()]);

    $this->getJson(route('api-unclaimed-profile.show', 'octocat'))
        ->assertOk()
        ->assertExactJson([
            'data' => [
                'id' => $profile->id,
                'handle' => 'OctoCat',
                'name' => 'The Octocat',
                'avatar_url' => 'https://avatars.githubusercontent.com/u/583231',
                'bio' => 'I am the Octocat.',
            ],
            'waiting_count' => 1,
            'is_waiting' => false,
            'waiting_you_follow' => [],
        ]);
});

it('tells a signed request whether its user waits, and up to three people they follow who do', function (): void {
    $profile = Profile::factory()->create(['handle' => 'octocat']);
    $member = User::factory()->create();
    $followed = User::factory()->count(4)->create()->sortBy('id')->values();
    $stranger = User::factory()->create();
    $member->following()->attach($followed->pluck('id'));
    DB::table('pending_follows')->insert($followed->push($stranger, $member)->map(fn (User $user): array => ['user_id' => $user->id, 'github_id' => $profile->github_id, 'created_at' => now()])->all());

    signedApiRequest(apiKeyPairFor($member), 'GET', route('api-unclaimed-profile.show', 'octocat'))
        ->assertOk()
        ->assertJsonPath('waiting_count', 6)
        ->assertJsonPath('is_waiting', true)
        ->assertJsonPath('waiting_you_follow', $followed->take(3)->map(fn (User $user): array => $user->only(['id', 'handle', 'name', 'avatar_url']))->all());
});

it('answers not found for a handle that is no unclaimed developer, without asking GitHub', function (Closure $handle): void {
    $this->getJson(route('api-unclaimed-profile.show', $handle()))->assertNotFound();
})->with([
    'a user' => fn (): string => User::factory()->create()->handle,
    'an organization' => fn (): string => Organization::factory()->create()->profile->handle,
    'unknown' => fn (): string => 'nobody',
]);
