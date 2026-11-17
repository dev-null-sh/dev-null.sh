<?php

declare(strict_types=1);

use App\Actions\DeleteScheduledCommit;
use App\Actions\UpdateScheduledCommitSubmittedAt;
use App\Enums\CommitStatus;
use App\Enums\PollDuration;
use App\Jobs\UnfurlLink;
use App\Models\Commit;
use App\Models\ScheduledCommit;
use App\Models\User;
use App\Models\UserTimeout;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    $this->travelTo(now()->setDate(2026, 10, 15)->setTime(12, 0));

    $this->user = User::factory()->create();
    $this->keyPair = apiKeyPairFor($this->user);
});

it("lists the user's queue, what is still waiting first, soonest first", function (): void {
    $later = ScheduledCommit::factory()->for($this->user)->create(['scheduled_at' => now()->addDays(2), 'body' => 'Later']);
    $sooner = ScheduledCommit::factory()->for($this->user)->create(['scheduled_at' => now()->addHour(), 'body' => 'Sooner', 'poll' => ['options' => ['Tabs', 'Spaces'], 'duration' => 1440]]);
    $submitted = ScheduledCommit::factory()->for($this->user)->create(['scheduled_at' => now()]);
    resolve(UpdateScheduledCommitSubmittedAt::class)->handle($submitted->id);
    $canceled = ScheduledCommit::factory()->for($this->user)->create();
    resolve(DeleteScheduledCommit::class)->handle($this->user, $canceled);
    ScheduledCommit::factory()->create();

    $response = signedApiRequest($this->keyPair, 'GET', route('api-scheduled-commit.index'));

    $response->assertOk()
        ->assertJsonPath('data.*.id', [$sooner->id, $later->id, $submitted->id])
        ->assertJsonPath('data.0.body', 'Sooner')
        ->assertJsonPath('data.0.poll_options', ['Tabs', 'Spaces'])
        ->assertJsonPath('data.0.image_count', 0)
        ->assertJsonPath('data.0.scheduled_at', now()->addHour()->toIso8601String())
        ->assertJsonPath('data.0.submitted_at', null)
        ->assertJsonPath('data.0.status', 'scheduled')
        ->assertJsonPath('data.2.body', null)
        ->assertJsonPath('data.2.body_segments', [])
        ->assertJsonPath('data.2.status', 'pending')
        ->assertJsonPath('meta.per_page', 20);
});

it('schedules a commit only the user sees until it is submitted', function (): void {
    $response = signedApiRequest($this->keyPair, 'POST', route('api-scheduled-commit.store'), [
        'body' => "Shipping tomorrow.\n\n\n\nStay tuned.",
        'scheduled_at' => now()->addHour()->setTimezone('Europe/Lisbon')->toIso8601String(),
    ]);

    $scheduled = ScheduledCommit::query()->sole();

    $response->assertCreated()
        ->assertJsonPath('data.id', $scheduled->id)
        ->assertJsonPath('data.body', "Shipping tomorrow.\n\nStay tuned.")
        ->assertJsonPath('data.scheduled_at', now()->addHour()->toIso8601String())
        ->assertJsonPath('data.status', 'scheduled');

    expect($scheduled->user_id)->toBe($this->user->id)
        ->and($scheduled->scheduled_at->equalTo(now()->addHour()))->toBeTrue()
        ->and(Commit::query()->count())->toBe(0);
});

it('schedules a poll that opens with its commit at the due time', function (): void {
    signedApiRequest($this->keyPair, 'POST', route('api-scheduled-commit.store'), [
        'body' => 'Tabs or spaces?',
        'scheduled_at' => now()->addHour()->toIso8601String(),
        'poll' => ['options' => ['Tabs', 'Spaces'], 'duration' => 1440],
    ])->assertCreated()
        ->assertJsonPath('data.poll_options', ['Tabs', 'Spaces']);

    $this->travel(1)->hour();
    $this->artisan('commits:submit-scheduled')->assertSuccessful();

    $poll = Commit::query()->sole()->poll;
    expect($poll->duration)->toBe(PollDuration::OneDay)
        ->and($poll->options()->orderBy('position')->pluck('label')->all())->toBe(['Tabs', 'Spaces']);
});

it('schedules images from a signed request, with their descriptions, and attaches them at the due time', function (): void {
    Storage::fake();

    signedApiRequestWithImages($this->keyPair, 'POST', route('api-scheduled-commit.store'), [
        'body' => 'Shipped the new dashboard.',
        'scheduled_at' => now()->addHour()->toIso8601String(),
        'image_descriptions' => ['The dashboard', null],
    ], [
        UploadedFile::fake()->image('wide.png', 300, 200),
        UploadedFile::fake()->image('tall.jpg', 100, 400),
    ])->assertCreated()
        ->assertJsonPath('data.image_count', 2);

    $this->travel(1)->hour();
    $this->artisan('commits:submit-scheduled')->assertSuccessful();

    expect(Commit::query()->sole()->images()->orderBy('position')->get()->map->only(['width', 'height', 'description'])->all())->toBe([
        ['width' => 300, 'height' => 200, 'description' => 'The dashboard'],
        ['width' => 100, 'height' => 400, 'description' => null],
    ]);
});

it('keeps the first link as the link card, unless the user chose another or none', function (array $choice, ?string $expected): void {
    Queue::fake([UnfurlLink::class]);

    signedApiRequest($this->keyPair, 'POST', route('api-scheduled-commit.store'), [
        'body' => 'Shipped https://example.com/first and https://example.com/second',
        'scheduled_at' => now()->addDay()->toIso8601String(),
        ...$choice,
    ])->assertCreated();

    $this->travel(1)->day();
    $this->artisan('commits:submit-scheduled')->assertSuccessful();

    expect(Commit::query()->sole()->linkPreview?->url)->toBe($expected);
})->with([
    'the first link' => [[], 'https://example.com/first'],
    'another link' => [['link_preview_url' => 'https://example.com/second'], 'https://example.com/second'],
    'no card' => [['link_preview_url' => null], null],
]);

it('rejects invalid commits as the web does', function (array $payload, string $field): void {
    signedApiRequest($this->keyPair, 'POST', route('api-scheduled-commit.store'), [
        'body' => 'Shipping tomorrow.',
        'scheduled_at' => now()->addHour()->toIso8601String(),
        ...$payload,
    ])->assertUnprocessable()
        ->assertJsonValidationErrors($field);

    expect(ScheduledCommit::query()->count())->toBe(0);
})->with([
    'no body' => [['body' => ''], 'body'],
    'no time' => [['scheduled_at' => null], 'scheduled_at'],
    'a time without an offset' => [['scheduled_at' => '2026-10-15T13:00:00'], 'scheduled_at'],
    'a time in the past' => [['scheduled_at' => '2026-10-15T11:00:00+00:00'], 'scheduled_at'],
    'a time beyond 30 days' => [['scheduled_at' => '2026-11-15T12:00:00+00:00'], 'scheduled_at'],
    'a reply' => [['parent_id' => '01a1823b-8a82-70e9-abce-622eea7c82cf'], 'parent_id'],
    'a quote' => [['quoted_commit_id' => '01a1823b-8a82-70e9-abce-622eea7c82cf'], 'quoted_commit_id'],
    'a link card for a link not in the body' => [['link_preview_url' => 'https://example.com'], 'link_preview_url'],
]);

it('limits how many commits the user may have waiting', function (): void {
    ScheduledCommit::factory()->for($this->user)->count(10)->create();

    signedApiRequest($this->keyPair, 'POST', route('api-scheduled-commit.store'), [
        'body' => 'One too many.',
        'scheduled_at' => now()->addHour()->toIso8601String(),
    ])->assertUnprocessable()
        ->assertJsonValidationErrors('scheduled_at');
});

it('may not schedule a commit while the user is in timeout', function (): void {
    UserTimeout::factory()->for($this->user)->create();

    signedApiRequest($this->keyPair, 'POST', route('api-scheduled-commit.store'), [
        'body' => 'Shipping tomorrow.',
        'scheduled_at' => now()->addHour()->toIso8601String(),
    ])->assertForbidden()
        ->assertJsonPath('code', 'timed_out');

    expect(ScheduledCommit::query()->count())->toBe(0);
});

it('cancels a scheduled commit, which is never submitted', function (): void {
    $scheduled = ScheduledCommit::factory()->for($this->user)->create(['scheduled_at' => now()]);

    signedApiRequest($this->keyPair, 'DELETE', route('api-scheduled-commit.destroy', $scheduled))->assertNoContent();

    $this->artisan('commits:submit-scheduled')->assertSuccessful();

    expect($scheduled->fresh()->canceled_at)->not->toBeNull()
        ->and(Commit::query()->count())->toBe(0);
});

it('tells the app to refresh when canceling one already submitted or canceled', function (Closure $settle): void {
    $scheduled = ScheduledCommit::factory()->for($this->user)->create(['scheduled_at' => now()]);
    $settle($scheduled);

    signedApiRequest($this->keyPair, 'DELETE', route('api-scheduled-commit.destroy', $scheduled))
        ->assertConflict()
        ->assertExactJson(['message' => 'This commit was already submitted or canceled. Refresh to see its status.']);
})->with([
    'submitted' => fn (ScheduledCommit $scheduled): bool => resolve(UpdateScheduledCommitSubmittedAt::class)->handle($scheduled->id),
    'canceled' => fn (ScheduledCommit $scheduled): bool => resolve(DeleteScheduledCommit::class)->handle($scheduled->user, $scheduled),
]);

it("may not cancel another user's scheduled commit", function (): void {
    $scheduled = ScheduledCommit::factory()->create();

    signedApiRequest($this->keyPair, 'DELETE', route('api-scheduled-commit.destroy', $scheduled))->assertNotFound();

    expect($scheduled->fresh()->canceled_at)->toBeNull();
});

it('requires a signed request', function (): void {
    $scheduled = ScheduledCommit::factory()->for($this->user)->create();

    $this->getJson(route('api-scheduled-commit.index'))->assertUnauthorized();
    $this->postJson(route('api-scheduled-commit.store'), [])->assertUnauthorized();
    $this->deleteJson(route('api-scheduled-commit.destroy', $scheduled))->assertUnauthorized();
});

it('reflects what became of the submitted commit', function (Closure $outcome, string $status): void {
    $scheduled = ScheduledCommit::factory()->for($this->user)->create(['scheduled_at' => now()]);
    resolve(UpdateScheduledCommitSubmittedAt::class)->handle($scheduled->id);
    $outcome(Commit::query()->sole());

    signedApiRequest($this->keyPair, 'GET', route('api-scheduled-commit.index'))
        ->assertOk()
        ->assertJsonPath('data.0.status', $status)
        ->assertJsonPath('data.0.body', null);
})->with([
    'approved' => [fn (Commit $commit): bool => $commit->update(['status' => CommitStatus::Approved]), 'approved'],
    'rejected' => [fn (Commit $commit): bool => $commit->update(['status' => CommitStatus::Rejected]), 'rejected'],
    'deleted' => [fn (Commit $commit): ?bool => $commit->delete(), 'deleted'],
]);
