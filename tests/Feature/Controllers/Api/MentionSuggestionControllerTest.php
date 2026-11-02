<?php

declare(strict_types=1);

use App\Models\Commit;
use App\Models\User;

beforeEach(function (): void {
    $this->user = User::factory()->create(['handle' => 'viewer', 'name' => 'Viewer']);
    $this->keyPair = apiKeyPairFor($this->user);
});

it('suggests the people the user follows without a term, most recently followed first', function (): void {
    $first = User::factory()->create();
    $second = User::factory()->create();
    User::factory()->create();

    $this->user->following()->attach($first, ['created_at' => now()->subMinute()]);
    $this->user->following()->attach($second, ['created_at' => now()]);

    signedApiRequest($this->keyPair, 'GET', route('api-mention-suggestion.index'))
        ->assertOk()
        ->assertJsonPath('data.*.id', [$second->id, $first->id])
        ->assertJsonMissingPath('data.0.email');
});

it('suggests matches, people the user follows first, leaving out muted people and people across a block', function (): void {
    User::factory()->create(['handle' => 'taylor', 'name' => 'Taylor']);
    $followed = User::factory()->create(['handle' => 'taylorotwell', 'name' => 'Taylor Otwell']);
    $muted = User::factory()->create(['handle' => 'taylor_muted']);
    $blocked = User::factory()->create(['handle' => 'taylor_blocked']);
    $blocker = User::factory()->create(['handle' => 'taylor_blocker']);

    $this->user->following()->attach($followed);
    $this->user->muting()->attach($muted);
    $this->user->blocking()->attach($blocked);
    $blocker->blocking()->attach($this->user);

    signedApiRequest($this->keyPair, 'GET', route('api-mention-suggestion.index', ['q' => '@tay']))
        ->assertOk()
        ->assertJsonPath('data.*.handle', ['taylorotwell', 'taylor']);
});

it("only suggests an org's members within it", function (): void {
    [$taylor, $jess] = followersOf($this->user, 2);
    $taylor->profile->update(['handle' => 'taylor']);
    $jess->profile->update(['handle' => 'jess']);
    User::factory()->create(['handle' => 'taylorotwell']);
    $org = createOrg($this->user, [$taylor, $jess]);

    signedApiRequest($this->keyPair, 'GET', route('api-mention-suggestion.index', ['q' => 'tay', 'org' => $org]))
        ->assertOk()
        ->assertJsonPath('data.*.handle', ['taylor']);
});

it("does not suggest an org's members to people outside it", function (): void {
    $mark = User::factory()->create();
    $org = createOrg($mark, followersOf($mark, 1));

    signedApiRequest($this->keyPair, 'GET', route('api-mention-suggestion.index', ['q' => '', 'org' => $org]))
        ->assertNotFound();
});

it('ranks the people in the thread replied to before ordinary matches', function (): void {
    $rootAuthor = User::factory()->create(['handle' => 'zroot', 'name' => 'Match Root']);
    $recipient = User::factory()->create(['handle' => 'zrecipient', 'name' => 'Match Recipient']);
    $outsider = User::factory()->create(['handle' => 'match', 'name' => 'Outsider']);
    $this->user->following()->attach($outsider);
    $root = Commit::factory()->for($rootAuthor)->create();
    $reply = Commit::factory()->for($recipient)->create(['parent_id' => $root->id]);

    signedApiRequest($this->keyPair, 'GET', route('api-mention-suggestion.index', ['q' => 'match', 'reply' => $reply->id]))
        ->assertOk()
        ->assertJsonPath('data.*.id', [$recipient->id, $rootAuthor->id, $outsider->id]);
});

it('ranks the quoted author before ordinary matches', function (): void {
    $author = User::factory()->create(['handle' => 'zauthor', 'name' => 'Match Author']);
    $followed = User::factory()->create(['handle' => 'match', 'name' => 'Followed']);
    $this->user->following()->attach($followed);
    $quoted = Commit::factory()->for($author)->create();

    signedApiRequest($this->keyPair, 'GET', route('api-mention-suggestion.index', ['quote' => $quoted->id]))
        ->assertOk()
        ->assertJsonPath('data.*.id', [$author->id, $followed->id]);
});

it('rejects invalid suggestion contexts', function (Closure $query, string $field): void {
    signedApiRequest($this->keyPair, 'GET', route('api-mention-suggestion.index', $query($this->user)))
        ->assertUnprocessable()
        ->assertJsonValidationErrors($field);
})->with([
    'an overly long term' => [fn (): array => ['q' => str_repeat('a', 101)], 'q'],
    'a malformed reply' => [fn (): array => ['reply' => 'not-a-uuid'], 'reply'],
    'a hidden reply' => [fn (): array => ['reply' => Commit::factory()->pending()->create()->id], 'reply'],
    'a hidden quote' => [fn (): array => ['quote' => Commit::factory()->pending()->create()->id], 'quote'],
    'a reply and a quote' => [function (): array {
        $commit = Commit::factory()->create();

        return ['reply' => $commit->id, 'quote' => $commit->id];
    }, 'reply'],
    'an org and a reply' => [fn (User $user): array => ['org' => createOrg($user, followersOf($user, 1)), 'reply' => Commit::factory()->create()->id], 'org'],
]);

it('requires a signed request', function (): void {
    $this->getJson(route('api-mention-suggestion.index', ['q' => 'nuno']))->assertUnauthorized();
});
