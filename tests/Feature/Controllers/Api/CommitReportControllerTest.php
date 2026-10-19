<?php

declare(strict_types=1);

use App\Enums\CommitStatus;
use App\Models\Commit;
use App\Models\CommitReport;
use App\Models\User;
use Illuminate\Support\Str;

beforeEach(function (): void {
    $this->user = User::factory()->create();
    $this->keyPair = apiKeyPairFor($this->user);
});

it('reports a commit privately, with its reason and details', function (): void {
    $commit = Commit::factory()->create();

    $response = signedApiRequest($this->keyPair, 'POST', route('api-commit-report.store', $commit), [
        'reason' => 'spam',
        'details' => 'Misleading package link.',
    ]);

    $response->assertNoContent();

    $report = CommitReport::query()->sole();

    expect($report->reporter_id)->toBe($this->user->id)
        ->and($report->commit_id)->toBe($commit->id)
        ->and($report->reason)->toBe('spam')
        ->and($report->details)->toBe('Misleading package link.')
        ->and($commit->refresh()->status)->toBe(CommitStatus::Approved);
});

it('takes each of the web reasons, with details optional', function (string $reason, array $details): void {
    $commit = Commit::factory()->create();

    signedApiRequest($this->keyPair, 'POST', route('api-commit-report.store', $commit), ['reason' => $reason, ...$details])
        ->assertNoContent();

    expect(CommitReport::query()->sole())
        ->reason->toBe($reason)
        ->details->toBeNull();
})->with(array_keys(CommitReport::REASONS))->with([
    'without details' => [[]],
    'with null details' => [['details' => null]],
    'with blank details' => [['details' => '   ']],
]);

it('refuses a missing or unknown reason, or details over 500 characters', function (array $data, string $field): void {
    $commit = Commit::factory()->create();

    signedApiRequest($this->keyPair, 'POST', route('api-commit-report.store', $commit), $data)
        ->assertUnprocessable()
        ->assertOnlyJsonValidationErrors($field);

    expect(CommitReport::query()->count())->toBe(0);
})->with([
    'no reason' => [['details' => 'Spam.'], 'reason'],
    'unknown reason' => [['reason' => 'approve'], 'reason'],
    'reason as a list' => [['reason' => ['spam']], 'reason'],
    'details too long' => [['reason' => 'spam', 'details' => Str::repeat('x', 501)], 'details'],
    'details as a list' => [['reason' => 'spam', 'details' => ['private']], 'details'],
]);

it('takes details of exactly 500 characters', function (): void {
    signedApiRequest($this->keyPair, 'POST', route('api-commit-report.store', Commit::factory()->create()), [
        'reason' => 'other',
        'details' => Str::repeat('x', 500),
    ])->assertNoContent();

    expect(CommitReport::query()->sole()->details)->toHaveLength(500);
});

it('may not report their own commit, with a code', function (): void {
    $commit = Commit::factory()->for($this->user)->create();

    signedApiRequest($this->keyPair, 'POST', route('api-commit-report.store', $commit), ['reason' => 'spam'])
        ->assertForbidden()
        ->assertExactJson(['message' => 'You cannot report your own commit.', 'code' => 'own_commit']);

    expect(CommitReport::query()->count())->toBe(0);
});

it('may not report a commit that is not published, even their own, with a code', function (string $status, bool $own): void {
    $commit = Commit::factory()->create(['status' => $status, 'user_id' => $own ? $this->user->id : User::factory()]);

    signedApiRequest($this->keyPair, 'POST', route('api-commit-report.store', $commit), ['reason' => 'spam'])
        ->assertNotFound()
        ->assertExactJson(['message' => "This commit isn't available.", 'code' => 'commit_unavailable']);

    expect(CommitReport::query()->count())->toBe(0);
})->with(['pending', 'rejected'])->with(["someone else's" => false, 'their own' => true]);

it('may not report a commit across a block, with a code', function (Closure $block): void {
    $commit = Commit::factory()->create();

    $block($this->user, $commit->user);

    signedApiRequest($this->keyPair, 'POST', route('api-commit-report.store', $commit), ['reason' => 'spam'])
        ->assertNotFound()
        ->assertJsonPath('code', 'commit_unavailable');

    expect(CommitReport::query()->count())->toBe(0);
})->with([
    'blocking' => fn (User $user, User $author) => $user->blocking()->attach($author),
    'blocked' => fn (User $user, User $author) => $author->blocking()->attach($user),
]);

it('may not report a deleted or missing commit, with a code', function (): void {
    $commit = Commit::factory()->create();
    $commit->delete();

    signedApiRequest($this->keyPair, 'POST', route('api-commit-report.store', $commit), ['reason' => 'spam'])
        ->assertNotFound()
        ->assertExactJson(['message' => "This commit isn't available.", 'code' => 'commit_unavailable']);

    signedApiRequest($this->keyPair, 'POST', route('api-commit-report.store', Str::uuid()->toString()), ['reason' => 'spam'])
        ->assertNotFound()
        ->assertExactJson(['message' => "This commit isn't available.", 'code' => 'commit_unavailable']);

    expect(CommitReport::query()->count())->toBe(0);
});

it('keeps the first report when the user reports a commit again, as on the web', function (): void {
    $commit = Commit::factory()->create();

    signedApiRequest($this->keyPair, 'POST', route('api-commit-report.store', $commit), ['reason' => 'spam'])->assertNoContent();

    $report = CommitReport::query()->sole();
    $report->forceFill(['dismissed_at' => now()])->save();

    signedApiRequest($this->keyPair, 'POST', route('api-commit-report.store', $commit), ['reason' => 'other', 'details' => 'Changed'])
        ->assertNoContent();

    expect(CommitReport::query()->count())->toBe(1)
        ->and($report->refresh()->reason)->toBe('spam')
        ->and($report->details)->toBeNull()
        ->and($report->dismissed_at)->not->toBeNull();
});

it('rejects requests that are not signed', function (): void {
    $commit = Commit::factory()->create();

    $this->postJson(route('api-commit-report.store', $commit), ['reason' => 'spam'])->assertUnauthorized();

    expect(CommitReport::query()->count())->toBe(0);
});

it('limits reports to 5 a minute, like the web, with a code', function (): void {
    $commit = Commit::factory()->create();

    foreach (range(1, 5) as $attempt) {
        signedApiRequest($this->keyPair, 'POST', route('api-commit-report.store', $commit), ['reason' => 'spam'])->assertNoContent();
    }

    signedApiRequest($this->keyPair, 'POST', route('api-commit-report.store', $commit), ['reason' => 'spam'])
        ->assertTooManyRequests()
        ->assertJsonPath('code', 'rate_limited');
});
