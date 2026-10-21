<?php

declare(strict_types=1);

use App\Enums\CommitStatus;
use App\Enums\NotificationType;
use App\Models\Commit;
use App\Models\User;

beforeEach(function (): void {
    $this->user = User::factory()->create();
    $this->keyPair = apiKeyPairFor($this->user);
});

it('stars a commit and answers with its new stars', function (): void {
    $commit = Commit::factory()->create();
    $commit->stars()->attach(User::factory()->create());

    $response = signedApiRequest($this->keyPair, 'POST', route('api-commit-star.store', $commit));

    $response->assertOk()
        ->assertJsonPath('data.id', $commit->id)
        ->assertJsonPath('data.stars_count', 2)
        ->assertJsonPath('data.starred', true);

    expect($this->user->hasStarred($commit))->toBeTrue()
        ->and($commit->user->notifications()->sole()->type)->toBe(NotificationType::Starred);
});

it('answers with the commit as the commit endpoint shows it', function (): void {
    $commit = Commit::factory()->create();
    Commit::factory()->for($commit, 'parent')->create();

    $response = signedApiRequest($this->keyPair, 'POST', route('api-commit-star.store', $commit));

    $response->assertOk()
        ->assertExactJson(['data' => signedApiRequest($this->keyPair, 'GET', route('api-commit.show', $commit))->json('data')]);
});

it('stars a commit only once', function (): void {
    $commit = Commit::factory()->create();

    signedApiRequest($this->keyPair, 'POST', route('api-commit-star.store', $commit))->assertOk();

    $response = signedApiRequest($this->keyPair, 'POST', route('api-commit-star.store', $commit));

    $response->assertOk()
        ->assertJsonPath('data.stars_count', 1)
        ->assertJsonPath('data.starred', true);

    expect($this->user->starredCommits()->count())->toBeOne();
});

it('may not star their own commit, as on the web', function (Closure $commit): void {
    $commit = $commit($this->user);

    $response = signedApiRequest($this->keyPair, 'POST', route('api-commit-star.store', $commit));

    $response->assertForbidden();

    expect($this->user->hasStarred($commit))->toBeFalse();
})->with([
    'approved' => fn (User $user): Commit => Commit::factory()->for($user)->create(),
    'pending' => fn (User $user): Commit => Commit::factory()->for($user)->pending()->create(),
]);

it('may not star a commit across a block', function (Closure $block): void {
    $commit = Commit::factory()->create();

    $block($this->user, $commit->user);

    signedApiRequest($this->keyPair, 'POST', route('api-commit-star.store', $commit))->assertNotFound();

    expect($this->user->hasStarred($commit))->toBeFalse();
})->with([
    'blocking' => fn (User $user, User $author) => $user->blocking()->attach($author),
    'blocked' => fn (User $user, User $author) => $author->blocking()->attach($user),
]);

it('may star the commits of someone the user mutes, as on the web', function (): void {
    $commit = Commit::factory()->create();

    $this->user->muting()->attach($commit->user);

    signedApiRequest($this->keyPair, 'POST', route('api-commit-star.store', $commit))
        ->assertOk()
        ->assertJsonPath('data.starred', true);
});

it('may not star a commit the user may not see', function (string $state): void {
    $commit = Commit::factory()->{$state}()->create();

    $response = signedApiRequest($this->keyPair, 'POST', route('api-commit-star.store', $commit));

    $response->assertNotFound();

    expect($this->user->hasStarred($commit))->toBeFalse();
})->with(['pending', 'rejected']);

it('may not star a deleted commit', function (): void {
    $commit = Commit::factory()->create();
    $commit->delete();

    $response = signedApiRequest($this->keyPair, 'POST', route('api-commit-star.store', $commit));

    $response->assertNotFound();

    expect($commit->stars()->count())->toBe(0);
});

it('unstars a commit and answers with its new stars', function (): void {
    $commit = Commit::factory()->create();
    $commit->stars()->attach([$this->user->id, User::factory()->create()->id]);

    $response = signedApiRequest($this->keyPair, 'DELETE', route('api-commit-star.destroy', $commit));

    $response->assertOk()
        ->assertJsonPath('data.id', $commit->id)
        ->assertJsonPath('data.stars_count', 1)
        ->assertJsonPath('data.starred', false);

    expect($this->user->hasStarred($commit))->toBeFalse();
});

it('unstars a commit that was not starred', function (): void {
    $commit = Commit::factory()->create();
    $commit->stars()->attach(User::factory()->create());

    $response = signedApiRequest($this->keyPair, 'DELETE', route('api-commit-star.destroy', $commit));

    $response->assertOk()
        ->assertJsonPath('data.stars_count', 1)
        ->assertJsonPath('data.starred', false);
});

it('may not unstar a commit the user may not see', function (): void {
    $commit = Commit::factory()->create();
    $commit->stars()->attach($this->user);
    $commit->update(['status' => CommitStatus::Rejected]);

    $response = signedApiRequest($this->keyPair, 'DELETE', route('api-commit-star.destroy', $commit));

    $response->assertNotFound()
        ->assertDontSee($commit->body);

    expect($this->user->hasStarred($commit))->toBeTrue();
});

it('may not unstar a deleted commit', function (): void {
    $commit = Commit::factory()->create();
    $commit->stars()->attach($this->user);
    $commit->delete();

    $response = signedApiRequest($this->keyPair, 'DELETE', route('api-commit-star.destroy', $commit));

    $response->assertNotFound();
});

it('returns not found for unknown commits', function (string $method): void {
    $response = signedApiRequest($this->keyPair, $method, 'api/v1/commits/0199e3a4-0000-7000-8000-000000000000/star');

    $response->assertNotFound();
})->with(['POST', 'DELETE']);

it('requires a signed request', function (string $method): void {
    $commit = Commit::factory()->create();

    $response = $this->json($method, "api/v1/commits/{$commit->id}/star");

    $response->assertUnauthorized();

    expect($commit->stars()->count())->toBe(0);
})->with(['POST', 'DELETE']);

it('limits stars and unstars to 60 a minute, like the web, with a code', function (): void {
    $commit = Commit::factory()->create();

    foreach (range(1, 60) as $attempt) {
        signedApiRequest($this->keyPair, $attempt % 2 === 0 ? 'DELETE' : 'POST', route('api-commit-star.store', $commit))->assertOk();
    }

    signedApiRequest($this->keyPair, 'POST', route('api-commit-star.store', $commit))
        ->assertTooManyRequests()
        ->assertExactJson(['message' => 'Too Many Attempts.', 'code' => 'rate_limited']);
});
