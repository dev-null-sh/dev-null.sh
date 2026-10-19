<?php

declare(strict_types=1);

use App\Models\Commit;
use App\Models\User;
use Illuminate\Support\Facades\RateLimiter;

beforeEach(function (): void {
    $this->user = User::factory()->create(['pasteis_mode' => true]);
    $this->keyPair = apiKeyPairFor($this->user);
});

it('gives a commit a pastel and answers with its count, what is left today and what its author has been given', function (): void {
    $this->travelTo(now()->setDate(2026, 10, 17)->setTime(15, 30));
    $author = User::factory()->create(['pasteis_mode' => false, 'pasteis_received' => 4]);
    $commit = Commit::factory()->for($author)->create(['pasteis_count' => 2]);

    $response = signedApiRequest($this->keyPair, 'POST', route('api-commit-pastel.store', $commit));

    $response->assertOk()
        ->assertJsonPath('data.id', $commit->id)
        ->assertJsonPath('data.pasteis_count', 3)
        ->assertJsonPath('pasteis_remaining', 9)
        ->assertJsonPath('pasteis_resets_at', '2026-10-18T00:00:00Z')
        ->assertJsonPath('author_pasteis_received', 5);

    expect($commit->refresh()->pasteis_count)->toBe(3)
        ->and($author->refresh()->pasteis_received)->toBe(5)
        ->and($this->user->refresh()->pasteisRemaining())->toBe(9);
});

it('answers with the commit as the commit endpoint shows it', function (): void {
    $commit = Commit::factory()->create();
    Commit::factory()->for($commit, 'parent')->create();

    $response = signedApiRequest($this->keyPair, 'POST', route('api-commit-pastel.store', $commit));

    $response->assertOk()
        ->assertJsonPath('data', signedApiRequest($this->keyPair, 'GET', route('api-commit.show', $commit))->json('data'));
});

it('gives ten a day across commits, then says the user ran out until midnight UTC', function (): void {
    $this->travelTo(now()->setDate(2026, 10, 16)->setTime(23, 59, 59));
    $commit = Commit::factory()->create();
    $otherCommit = Commit::factory()->create();

    for ($given = 1; $given <= User::DAILY_PASTEIS; $given++) {
        signedApiRequest($this->keyPair, 'POST', route('api-commit-pastel.store', $commit))
            ->assertOk()
            ->assertJsonPath('data.pasteis_count', $given)
            ->assertJsonPath('pasteis_remaining', User::DAILY_PASTEIS - $given);
    }

    signedApiRequest($this->keyPair, 'POST', route('api-commit-pastel.store', $otherCommit))
        ->assertUnprocessable()
        ->assertExactJson(['message' => 'you ran out of pastéis. wait until tomorrow.', 'code' => 'pasteis_exhausted']);

    expect($otherCommit->refresh()->pasteis_count)->toBe(0);

    $this->travel(1)->seconds();

    signedApiRequest($this->keyPair, 'POST', route('api-commit-pastel.store', $otherCommit))
        ->assertOk()
        ->assertJsonPath('data.pasteis_count', 1)
        ->assertJsonPath('pasteis_remaining', 9)
        ->assertJsonPath('pasteis_resets_at', '2026-10-18T00:00:00Z');
});

it('may not give a pastel with pastéis mode off', function (): void {
    $this->user->update(['pasteis_mode' => false]);
    $commit = Commit::factory()->create();

    signedApiRequest($this->keyPair, 'POST', route('api-commit-pastel.store', $commit))
        ->assertForbidden()
        ->assertJsonPath('message', 'Turn on pastéis mode to give pastéis.');

    expect($commit->refresh()->pasteis_count)->toBe(0)
        ->and($this->user->refresh()->pasteisRemaining())->toBe(User::DAILY_PASTEIS);
});

it('may not give their own commit a pastel, as on the web', function (Closure $commit): void {
    $commit = $commit($this->user);

    signedApiRequest($this->keyPair, 'POST', route('api-commit-pastel.store', $commit))
        ->assertForbidden()
        ->assertJsonPath('message', 'You cannot award your own commit.');

    expect($commit->refresh()->pasteis_count)->toBe(0)
        ->and($this->user->refresh()->pasteis_received)->toBe(0);
})->with([
    'approved' => fn (User $user): Commit => Commit::factory()->for($user)->create(),
    'pending' => fn (User $user): Commit => Commit::factory()->for($user)->pending()->create(),
]);

it('may not give a pastel to a commit the user may not see, without spending one', function (string $state): void {
    $commit = Commit::factory()->{$state}()->create();

    signedApiRequest($this->keyPair, 'POST', route('api-commit-pastel.store', $commit))->assertNotFound();

    expect($commit->refresh()->pasteis_count)->toBe(0)
        ->and($this->user->refresh()->pasteisRemaining())->toBe(User::DAILY_PASTEIS);
})->with(['pending', 'rejected', 'trashed']);

it('may not give a pastel across a block', function (Closure $block): void {
    $commit = Commit::factory()->create();

    $block($this->user, $commit->user);

    signedApiRequest($this->keyPair, 'POST', route('api-commit-pastel.store', $commit))->assertNotFound();

    expect($commit->refresh()->pasteis_count)->toBe(0);
})->with([
    'blocking' => fn (User $user, User $author) => $user->blocking()->attach($author),
    'blocked' => fn (User $user, User $author) => $author->blocking()->attach($user),
]);

it('shares the web limit of 60 a minute', function (): void {
    $commit = Commit::factory()->create();

    RateLimiter::increment(sha1((string) $this->user->id), amount: 60);

    signedApiRequest($this->keyPair, 'POST', route('api-commit-pastel.store', $commit))
        ->assertTooManyRequests()
        ->assertJsonPath('code', 'rate_limited');

    expect($commit->refresh()->pasteis_count)->toBe(0);
});

it('rejects requests that are not signed', function (): void {
    $commit = Commit::factory()->create();

    $this->postJson(route('api-commit-pastel.store', $commit))->assertUnauthorized();

    expect($commit->refresh()->pasteis_count)->toBe(0);
});
