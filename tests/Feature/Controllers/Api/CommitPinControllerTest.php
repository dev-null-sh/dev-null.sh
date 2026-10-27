<?php

declare(strict_types=1);

use App\Models\Commit;
use App\Models\User;

beforeEach(function (): void {
    $this->user = User::factory()->create();
    $this->keyPair = apiKeyPairFor($this->user);
});

it('pins an own commit and answers with it pinned', function (): void {
    $commit = Commit::factory()->for($this->user)->create();

    $response = signedApiRequest($this->keyPair, 'PUT', route('api-commit-pin.update', $commit));

    $response->assertOk()
        ->assertJsonPath('data.id', $commit->id)
        ->assertJsonPath('data.is_pinned', true);

    expect($this->user->fresh()->pinned_commit_id)->toBe($commit->id);
});

it('answers with the commit as the commit endpoint shows it', function (): void {
    $commit = Commit::factory()->for($this->user)->create();
    Commit::factory()->for($commit, 'parent')->create();

    $response = signedApiRequest($this->keyPair, 'PUT', route('api-commit-pin.update', $commit));

    $response->assertOk()
        ->assertExactJson(['data' => signedApiRequest($this->keyPair, 'GET', route('api-commit.show', $commit))->json('data')]);
});

it('replaces the pin, as on the web', function (): void {
    $first = Commit::factory()->for($this->user)->create();
    $replacement = Commit::factory()->for($this->user)->pending()->create();

    signedApiRequest($this->keyPair, 'PUT', route('api-commit-pin.update', $first))->assertOk();

    $response = signedApiRequest($this->keyPair, 'PUT', route('api-commit-pin.update', $replacement));

    $response->assertOk()->assertJsonPath('data.is_pinned', true);

    expect($this->user->fresh()->pinned_commit_id)->toBe($replacement->id);
});

it('unpins an own commit and answers with it unpinned', function (): void {
    $commit = Commit::factory()->for($this->user)->create();
    $this->user->forceFill(['pinned_commit_id' => $commit->id])->save();

    $response = signedApiRequest($this->keyPair, 'DELETE', route('api-commit-pin.destroy', $commit));

    $response->assertOk()
        ->assertJsonPath('data.id', $commit->id)
        ->assertJsonPath('data.is_pinned', false);

    expect($this->user->fresh()->pinned_commit_id)->toBeNull();
});

it('does not clear a replacement on a stale unpin', function (): void {
    $first = Commit::factory()->for($this->user)->create();
    $replacement = Commit::factory()->for($this->user)->create();
    $this->user->forceFill(['pinned_commit_id' => $replacement->id])->save();

    $response = signedApiRequest($this->keyPair, 'DELETE', route('api-commit-pin.destroy', $first));

    $response->assertOk()->assertJsonPath('data.is_pinned', false);

    expect($this->user->fresh()->pinned_commit_id)->toBe($replacement->id);
});

it('may not pin or unpin the commit of someone else', function (string $method): void {
    $commit = Commit::factory()->create();
    $commit->user->forceFill(['pinned_commit_id' => $commit->id])->save();

    signedApiRequest($this->keyPair, $method, "api/v1/commits/{$commit->id}/pin")->assertForbidden();

    expect($commit->user->fresh()->pinned_commit_id)->toBe($commit->id)
        ->and($this->user->fresh()->pinned_commit_id)->toBeNull();
})->with(['PUT', 'DELETE']);

it('may not pin or unpin a reply', function (string $method): void {
    $reply = Commit::factory()->for($this->user)->for(Commit::factory(), 'parent')->create();

    signedApiRequest($this->keyPair, $method, "api/v1/commits/{$reply->id}/pin")->assertForbidden();

    expect($this->user->fresh()->pinned_commit_id)->toBeNull();
})->with(['PUT', 'DELETE']);

it('may not pin or unpin a deleted commit', function (string $method): void {
    $commit = Commit::factory()->for($this->user)->create();
    $commit->delete();

    signedApiRequest($this->keyPair, $method, "api/v1/commits/{$commit->id}/pin")->assertNotFound();

    expect($this->user->fresh()->pinned_commit_id)->toBeNull();
})->with(['PUT', 'DELETE']);

it('returns not found for unknown commits', function (string $method): void {
    $response = signedApiRequest($this->keyPair, $method, 'api/v1/commits/0199e3a4-0000-7000-8000-000000000000/pin');

    $response->assertNotFound();
})->with(['PUT', 'DELETE']);

it('requires a signed request', function (string $method): void {
    $commit = Commit::factory()->for($this->user)->create();

    $response = $this->json($method, "api/v1/commits/{$commit->id}/pin");

    $response->assertUnauthorized();

    expect($this->user->fresh()->pinned_commit_id)->toBeNull();
})->with(['PUT', 'DELETE']);

it('limits pins and unpins to 20 a minute, like the web, with a code', function (): void {
    $commit = Commit::factory()->for($this->user)->create();

    foreach (range(1, 20) as $attempt) {
        signedApiRequest($this->keyPair, $attempt % 2 === 0 ? 'DELETE' : 'PUT', route('api-commit-pin.update', $commit))->assertOk();
    }

    signedApiRequest($this->keyPair, 'PUT', route('api-commit-pin.update', $commit))
        ->assertTooManyRequests()
        ->assertExactJson(['message' => 'Too Many Attempts.', 'code' => 'rate_limited']);
});
