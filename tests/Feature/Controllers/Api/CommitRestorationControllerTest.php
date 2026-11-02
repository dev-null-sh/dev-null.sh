<?php

declare(strict_types=1);

use App\Actions\DeleteCommit;
use App\Enums\CommitStatus;
use App\Models\Commit;
use App\Models\User;

beforeEach(function (): void {
    $this->user = User::factory()->create();
    $this->keyPair = apiKeyPairFor($this->user);
});

it('restores a deleted commit within thirty seconds and answers with it', function (CommitStatus $status): void {
    $commit = Commit::factory()->for($this->user)->create(['status' => $status]);
    resolve(DeleteCommit::class)->handle($commit);
    $this->travel(29)->seconds();

    $response = signedApiRequest($this->keyPair, 'POST', route('api-commit-restoration.store', $commit));

    $response->assertOk()
        ->assertJsonPath('data.id', $commit->id)
        ->assertJsonPath('data.status', $status->value);

    $this->assertNotSoftDeleted($commit);
})->with(CommitStatus::cases());

it('answers with the restored commit as the commit endpoint shows it', function (): void {
    $commit = Commit::factory()->for($this->user)->create();
    Commit::factory()->for($commit, 'parent')->create();
    resolve(DeleteCommit::class)->handle($commit);

    $response = signedApiRequest($this->keyPair, 'POST', route('api-commit-restoration.store', $commit));

    $response->assertOk()
        ->assertExactJson(['data' => signedApiRequest($this->keyPair, 'GET', route('api-commit.show', $commit))->json('data')]);
});

it('rejects undo at and after the deadline', function (int $seconds): void {
    $this->travelTo(now()->startOfSecond());
    $commit = Commit::factory()->for($this->user)->create();
    resolve(DeleteCommit::class)->handle($commit);
    $this->travel($seconds)->seconds();

    signedApiRequest($this->keyPair, 'POST', route('api-commit-restoration.store', $commit))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['undo' => 'The 30-second undo window has expired.']);

    $this->assertSoftDeleted($commit);
})->with([30, 31, 3600]);

it('rejects a commit that is not deleted, and a repeated undo', function (): void {
    $commit = Commit::factory()->for($this->user)->create();

    signedApiRequest($this->keyPair, 'POST', route('api-commit-restoration.store', $commit))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['undo' => 'This commit is no longer deleted.']);

    resolve(DeleteCommit::class)->handle($commit);

    signedApiRequest($this->keyPair, 'POST', route('api-commit-restoration.store', $commit))->assertOk();
    signedApiRequest($this->keyPair, 'POST', route('api-commit-restoration.store', $commit))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['undo' => 'This commit is no longer deleted.']);

    $this->assertNotSoftDeleted($commit);
});

it('hides the deleted commits of others', function (): void {
    $commit = Commit::factory()->create();
    resolve(DeleteCommit::class)->handle($commit);

    signedApiRequest($this->keyPair, 'POST', route('api-commit-restoration.store', $commit))->assertNotFound();

    $this->assertSoftDeleted($commit);
});

it('cannot restore a force deleted commit', function (): void {
    $commit = Commit::factory()->for($this->user)->create();
    $commit->forceDelete();

    signedApiRequest($this->keyPair, 'POST', route('api-commit-restoration.store', $commit))->assertNotFound();
});

it('requires a signed request', function (): void {
    $commit = Commit::factory()->for($this->user)->create();
    resolve(DeleteCommit::class)->handle($commit);

    $this->postJson(route('api-commit-restoration.store', $commit))->assertUnauthorized();

    $this->assertSoftDeleted($commit);
});
