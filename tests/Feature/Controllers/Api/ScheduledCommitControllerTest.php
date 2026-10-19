<?php

declare(strict_types=1);

use App\Enums\ModerationReason;
use App\Models\Commit;
use App\Models\ScheduledCommit;
use App\Models\User;
use App\Models\UserTimeout;
use App\Services\CommitBodies;

beforeEach(function (): void {
    $this->travelTo(now()->setDate(2026, 10, 15)->setTime(12, 0));
    $this->user = User::factory()->create();
    $this->keyPair = apiKeyPairFor($this->user);
});

it('schedules a commit in UTC, without committing it, and answers with it', function (): void {
    $response = signedApiRequest($this->keyPair, 'POST', route('api-scheduled-commit.store'), [
        'body' => '  Shipped the parser benchmark.  ',
        'scheduled_at' => '2026-10-16T10:30:00+05:30',
    ]);

    $scheduled = ScheduledCommit::query()->sole();

    $response->assertCreated()
        ->assertExactJson([
            'data' => [
                'id' => $scheduled->id,
                'body' => 'Shipped the parser benchmark.',
                ...resolve(CommitBodies::class)->segments('Shipped the parser benchmark.'),
                'poll_options' => null,
                'image_count' => 0,
                'scheduled_at' => '2026-10-16T05:00:00Z',
                'submitted_at' => null,
                'status' => 'scheduled',
                'commit_id' => null,
            ],
        ]);

    expect($scheduled->user_id)->toBe($this->user->id)
        ->and($scheduled->scheduled_at->toIso8601String())->toBe('2026-10-16T05:00:00+00:00')
        ->and(Commit::query()->count())->toBe(0);
});

it('submits it for review at the due time', function (): void {
    signedApiRequest($this->keyPair, 'POST', route('api-scheduled-commit.store'), [
        'body' => 'Shipped the parser benchmark.',
        'scheduled_at' => '2026-10-15T13:00:00+00:00',
    ])->assertCreated();

    $this->travelTo(now()->setTime(13, 0));
    $this->artisan('commits:submit-scheduled')->assertSuccessful();

    expect(Commit::query()->sole())
        ->user_id->toBe($this->user->id)
        ->body->toBe('Shipped the parser benchmark.')
        ->and(ScheduledCommit::query()->sole()->submitted_at)->not->toBeNull();
});

it('splits a long body like a commit, with the excerpt a list shows', function (): void {
    $body = str_repeat('Shipped a long changelog entry. ', 40);

    $response = signedApiRequest($this->keyPair, 'POST', route('api-scheduled-commit.store'), [
        'body' => $body,
        'scheduled_at' => '2026-10-16T12:00:00+00:00',
    ]);

    $segments = resolve(CommitBodies::class)->segments(mb_trim($body));

    $response->assertCreated()
        ->assertJsonPath('data.body_segments', $segments['body_segments'])
        ->assertJsonPath('data.preview_segments', $segments['preview_segments']);

    expect($segments['preview_segments'])->not->toBeNull();
});

it('rejects invalid times and bodies, as the web does', function (array $payload, string $field): void {
    $response = signedApiRequest($this->keyPair, 'POST', route('api-scheduled-commit.store'), [
        'body' => 'Shipped a scheduling feature.',
        'scheduled_at' => '2026-10-16T12:00:00+00:00',
        ...$payload,
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors($field);

    expect(ScheduledCommit::query()->count())->toBe(0);
})->with([
    'missing time' => [['scheduled_at' => null], 'scheduled_at'],
    'missing offset' => [['scheduled_at' => '2026-10-16T12:00:00'], 'scheduled_at'],
    'invalid date' => [['scheduled_at' => '2026-10-32T12:00:00+00:00'], 'scheduled_at'],
    'now' => [['scheduled_at' => '2026-10-15T12:00:00+00:00'], 'scheduled_at'],
    'past' => [['scheduled_at' => '2026-10-15T11:59:00+00:00'], 'scheduled_at'],
    'beyond thirty days' => [['scheduled_at' => '2026-11-14T12:00:01+00:00'], 'scheduled_at'],
    'missing body' => [['body' => null], 'body'],
    'whitespace' => [['body' => '    '], 'body'],
    'too long' => [['body' => str_repeat('a', 4001)], 'body'],
]);

it('accepts the end of the window and a unicode body', function (): void {
    signedApiRequest($this->keyPair, 'POST', route('api-scheduled-commit.store'), [
        'body' => str_repeat('é', 4000),
        'scheduled_at' => '2026-11-14T12:00:00+00:00',
    ])->assertCreated();

    expect(ScheduledCommit::query()->count())->toBe(1);
});

it('does not schedule replies or quotes', function (string $field): void {
    $commit = Commit::factory()->create();

    $response = signedApiRequest($this->keyPair, 'POST', route('api-scheduled-commit.store'), [
        'body' => 'This must not become a reply or a quote.',
        'scheduled_at' => '2026-10-16T12:00:00+00:00',
        $field => $commit->id,
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors($field);

    expect(ScheduledCommit::query()->count())->toBe(0);
})->with(['parent_id', 'quoted_commit_id']);

it('keeps to 10 scheduled commits waiting at once', function (): void {
    ScheduledCommit::factory()->for($this->user)->count(10)->create();

    $response = signedApiRequest($this->keyPair, 'POST', route('api-scheduled-commit.store'), [
        'body' => 'One more scheduled commit.',
        'scheduled_at' => '2026-10-16T12:00:00+00:00',
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['scheduled_at' => 'You can have up to 10 scheduled commits. Cancel one before scheduling another.']);

    expect(ScheduledCommit::query()->count())->toBe(10);
});

it('shares the limit of 10 commits a minute', function (): void {
    for ($i = 0; $i < 10; $i++) {
        signedApiRequest($this->keyPair, 'POST', route('api-commit.store'), ['body' => 'An ordinary commit.'])->assertCreated();
    }

    $response = signedApiRequest($this->keyPair, 'POST', route('api-scheduled-commit.store'), [
        'body' => 'Must be throttled.',
        'scheduled_at' => '2026-10-16T12:00:00+00:00',
    ]);

    $response->assertTooManyRequests()
        ->assertJsonPath('code', 'rate_limited')
        ->assertHeader('Retry-After');

    expect(ScheduledCommit::query()->count())->toBe(0);
});

it('does not schedule for a user in timeout', function (): void {
    UserTimeout::factory()->for($this->user)->create(['reason' => ModerationReason::Spam, 'ends_at' => now()->addDays(2)]);

    signedApiRequest($this->keyPair, 'POST', route('api-scheduled-commit.store'), [
        'body' => 'It was DNS.',
        'scheduled_at' => '2026-10-16T12:00:00+00:00',
    ])->assertForbidden()
        ->assertJsonPath('code', 'timed_out');

    expect(ScheduledCommit::query()->count())->toBe(0);
});

it('leaves the first commit to the web, where the commit rules are read', function (): void {
    $user = User::factory()->firstTimePoster()->create();

    signedApiRequest(apiKeyPairFor($user), 'POST', route('api-scheduled-commit.store'), [
        'body' => 'Hello, world.',
        'scheduled_at' => '2026-10-16T12:00:00+00:00',
    ])->assertForbidden()
        ->assertJsonPath('code', 'commit_rules_required');

    expect(ScheduledCommit::query()->count())->toBe(0);
});

it('requires a signed request', function (): void {
    $this->postJson(route('api-scheduled-commit.store'), [
        'body' => 'Shipped the parser benchmark.',
        'scheduled_at' => '2026-10-16T12:00:00+00:00',
    ])->assertUnauthorized();

    expect(ScheduledCommit::query()->count())->toBe(0);
});
