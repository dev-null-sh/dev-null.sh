<?php

declare(strict_types=1);

use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\User;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

beforeEach(function (): void {
    $this->user = User::factory()->create();
    $this->keyPair = apiKeyPairFor($this->user);

    // The reason each person matching the search can't be picked, by their handle...
    $this->reasons = fn (array $query): array => collect(signedApiRequest($this->keyPair, 'GET', route('api-remote-candidate.index', $query))->assertOk()->json('candidates'))
        ->mapWithKeys(fn (array $candidate): array => [$candidate['user']['handle'] => $candidate['reason']])
        ->all();
});

it('finds people to push to, as the api shows people elsewhere', function (): void {
    $nuno = User::factory()->create(['handle' => 'nunomaduro', 'bio' => 'Pest and Pint.']);
    $nuno->following()->attach($this->user);

    signedApiRequest($this->keyPair, 'GET', route('api-remote-candidate.index', ['q' => '@NunoMaduro', 'for' => 'remote']))
        ->assertOk()
        ->assertHeader('Cache-Control', 'no-store, private')
        ->assertExactJson(['candidates' => [[
            'user' => [
                'id' => $nuno->id,
                'handle' => 'nunomaduro',
                'username' => 'nunomaduro',
                'name' => $nuno->name,
                'avatar_url' => $nuno->avatar_url,
                'bio' => 'Pest and Pint.',
                'company' => $nuno->company,
                'location' => $nuno->location,
                'website' => $nuno->website,
                'github_url' => 'https://github.com/nunomaduro',
                'html_url' => route('user.show', $nuno),
                'feed_url' => route('user-feed.show', $nuno),
                'created_at' => $nuno->created_at->toIso8601ZuluString(),
                'lane' => $nuno->lane(),
            ],
            'reason' => null,
        ]]]);
});

it('says why someone cannot be pushed to', function (array $query): void {
    $follower = User::factory()->create(['handle' => 'ana_follows']);
    $follower->following()->attach($this->user);
    User::factory()->create(['handle' => 'ana_stranger']);
    $off = User::factory()->create(['handle' => 'ana_off', 'remotes_enabled' => false]);
    $off->following()->attach($this->user);

    expect(($this->reasons)(['q' => 'ana', ...$query]))->toEqualCanonicalizing([
        'ana_follows' => null,
        'ana_stranger' => "doesn't follow you",
        'ana_off' => 'has remotes off',
    ]);
})->with([
    'by default' => [[]],
    'for a remote' => [['for' => 'remote']],
]);

it('says why someone cannot join an org', function (Closure $setUp, string $reason): void {
    [$nuno] = followersOf($this->user, 1);
    $org = createOrg($this->user, [$nuno]);
    $candidate = User::factory()->create(['handle' => 'candidate']);
    $candidate->following()->attach($this->user);

    $setUp($candidate, $nuno, $org);

    expect(($this->reasons)(['q' => 'candidate', 'org' => $org]))->toBe(['candidate' => $reason]);
})->with([
    'already in the org' => [fn (User $candidate, User $nuno, string $org) => ConversationParticipant::factory()->create(['conversation_id' => $org, 'user_id' => $candidate->id]), 'already in the org'],
    'remotes off' => [fn (User $candidate) => $candidate->update(['remotes_enabled' => false]), 'has remotes off'],
    'not a follower' => [fn (User $candidate) => $candidate->following()->detach(), "doesn't follow you"],
    'a block with a member' => [fn (User $candidate, User $nuno) => $nuno->blocking()->attach($candidate), 'has a block with someone in the org'],
    'a full org' => [fn (User $candidate, User $nuno, string $org) => ConversationParticipant::factory()->count(Conversation::MAX_MEMBERS - 2)->create(['conversation_id' => $org]), 'the org is full ('.Conversation::MAX_MEMBERS.')'],
]);

it('lets anyone who can join a new org be picked for it', function (): void {
    [$nuno] = followersOf($this->user, 1);
    $nuno->profile->update(['handle' => 'ana_member']);
    $org = createOrg($this->user, [$nuno]);
    User::factory()->create(['handle' => 'ana_stranger']);

    expect(($this->reasons)(['q' => 'ana', 'org' => $org]))->toEqualCanonicalizing(['ana_member' => 'already in the org', 'ana_stranger' => "doesn't follow you"])
        ->and(($this->reasons)(['q' => 'ana', 'for' => 'org']))->toEqualCanonicalizing(['ana_member' => null, 'ana_stranger' => "doesn't follow you"]);
});

it('finds nobody without a search, and never the user themselves', function (): void {
    $this->user->profile->update(['handle' => 'ana_me']);

    signedApiRequest($this->keyPair, 'GET', route('api-remote-candidate.index'))->assertOk()->assertExactJson(['candidates' => []]);
    signedApiRequest($this->keyPair, 'GET', route('api-remote-candidate.index', ['q' => 'ana_me']))->assertOk()->assertExactJson(['candidates' => []]);
});

it('keeps people out of orgs they are not in', function (): void {
    $nuno = User::factory()->create();
    [$taylor] = followersOf($nuno, 1);
    $org = createOrg($nuno, [$taylor]);

    signedApiRequest($this->keyPair, 'GET', route('api-remote-candidate.index', ['q' => 'ana', 'org' => $org]))->assertNotFound();
    signedApiRequest($this->keyPair, 'GET', route('api-remote-candidate.index', ['q' => 'ana', 'org' => Str::uuid()->toString()]))->assertNotFound();
});

it('refuses a search it cannot take', function (array $query, string $field): void {
    signedApiRequest($this->keyPair, 'GET', route('api-remote-candidate.index', $query))
        ->assertUnprocessable()
        ->assertOnlyJsonValidationErrors($field);
})->with([
    'a long search' => [['q' => Str::repeat('a', 101)], 'q'],
    'an unknown purpose' => [['q' => 'ana', 'for' => 'commit'], 'for'],
    'an org that is not a uuid' => [['q' => 'ana', 'org' => '1'], 'org'],
    'an org for a new org that is not a uuid' => [['q' => 'ana', 'for' => 'org', 'org' => 'core'], 'org'],
]);

it('is not there while the user has Remotes off', function (): void {
    $this->user->update(['remotes_enabled' => false]);

    signedApiRequest($this->keyPair, 'GET', route('api-remote-candidate.index', ['q' => 'ana']))->assertNotFound();
});

it('shares the web limit of 300 Remotes requests a minute', function (): void {
    RateLimiter::increment(md5('conversations'.$this->user->id), amount: 300);

    signedApiRequest($this->keyPair, 'GET', route('api-remote-candidate.index', ['q' => 'ana']))
        ->assertTooManyRequests()
        ->assertJsonPath('code', 'rate_limited');
});

it('rejects requests that are not signed', function (): void {
    $this->getJson(route('api-remote-candidate.index', ['q' => 'ana']))->assertUnauthorized();
});
