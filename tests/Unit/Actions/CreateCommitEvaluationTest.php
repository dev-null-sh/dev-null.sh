<?php

declare(strict_types=1);

use App\Actions\CreateCommitEvaluation;
use App\Actions\UpdateCommitStatuses;
use App\Enums\CommitStatus;
use App\Enums\EvaluationDecision;
use App\Enums\NotificationType;
use App\Models\Commit;
use App\Models\CommitEvaluation;
use App\Models\LinkPreview;
use App\Models\ModerationRule;
use App\Models\Notification;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    config(['services.typesafe.key' => 'test-key', 'services.typesafe.enabled' => true]);
});

/**
 * @param  array<string, float>  $probabilities
 */
function fakeJev(array $probabilities): void
{
    Http::fake(['api.typesafe.ai/*' => Http::response(jevAnswers($probabilities), 200, ['x-typesafe-request-id' => 'req_123'])]);
}

it('rejects a commit a live reject rule is sure about, telling the author why', function (): void {
    $spam = ModerationRule::factory()->create();
    $friendly = ModerationRule::factory()->approving()->create();
    $commit = Commit::factory()->pending()->create(['body' => 'Buy 1,000 GitHub stars for $5']);

    fakeJev(["rule_{$spam->id}" => 0.99, "rule_{$friendly->id}" => 0.02]);

    $evaluation = resolve(CreateCommitEvaluation::class)->handle($commit);

    expect($commit->refresh()->status)->toBe(CommitStatus::Rejected)
        ->and($evaluation)
        ->decision->toBe(EvaluationDecision::Rejected)
        ->test_decision->toBe(EvaluationDecision::Rejected)
        ->model->toBe('jev-1.13.0')
        ->request_id->toBe('req_123')
        ->input_tokens->toBe(1646)
        ->latency_ms->toBeInt()
        ->error->toBeNull()
        ->and($evaluation->refresh()->results)->toBe([
            ['rule_id' => $spam->id, 'name' => 'Spam and self-promotion', 'action' => 'reject', 'mode' => 'live', 'probability' => 0.99, 'outcome' => 'reject', 'act_threshold' => 90, 'review_threshold' => 50],
            ['rule_id' => $friendly->id, 'name' => 'Friendly dev content', 'action' => 'approve', 'mode' => 'live', 'probability' => 0.02, 'outcome' => 'none', 'act_threshold' => 90, 'review_threshold' => null],
        ])
        ->and(Notification::query()->sole())
        ->type->toBe(NotificationType::Rejected)
        ->recipient_id->toBe($commit->user_id)
        ->reason->toBe('This looks like spam or self-promotion.');
});

it('approves a commit every live approve rule is sure about, telling the author', function (): void {
    $spam = ModerationRule::factory()->create();
    $friendly = ModerationRule::factory()->approving()->create();
    $commit = Commit::factory()->pending()->create(['body' => 'Upgraded from Laravel 10 to 13 this weekend.']);

    fakeJev(["rule_{$spam->id}" => 0.09, "rule_{$friendly->id}" => 0.97]);

    $evaluation = resolve(CreateCommitEvaluation::class)->handle($commit);

    expect($commit->refresh()->status)->toBe(CommitStatus::Approved)
        ->and($evaluation->decision)->toBe(EvaluationDecision::Approved)
        ->and(Notification::query()->sole())
        ->type->toBe(NotificationType::Approved)
        ->reason->toBeNull();
});

it('leaves a commit it is unsure about to the moderators', function (): void {
    $harassment = ModerationRule::factory()->create(['name' => 'Harassment']);
    $friendly = ModerationRule::factory()->approving()->create();
    $commit = Commit::factory()->pending()->create(['body' => 'Anyone still using PHP in 2026 is a clown.']);

    fakeJev(["rule_{$harassment->id}" => 0.69, "rule_{$friendly->id}" => 0.08]);

    $evaluation = resolve(CreateCommitEvaluation::class)->handle($commit);

    expect($commit->refresh()->status)->toBe(CommitStatus::Pending)
        ->and($evaluation->decision)->toBe(EvaluationDecision::Review)
        ->and(Notification::query()->count())->toBe(0);
});

it('records test rules without ever acting on them', function (): void {
    $spam = ModerationRule::factory()->testing()->create();
    $friendly = ModerationRule::factory()->approving()->testing()->create();
    $commit = Commit::factory()->pending()->create(['body' => 'Buy 1,000 GitHub stars for $5']);

    fakeJev(["rule_{$spam->id}" => 0.99, "rule_{$friendly->id}" => 0.02]);

    $evaluation = resolve(CreateCommitEvaluation::class)->handle($commit);

    expect($commit->refresh()->status)->toBe(CommitStatus::Pending)
        ->and($evaluation)
        ->decision->toBe(EvaluationDecision::Review)
        ->test_decision->toBe(EvaluationDecision::Rejected)
        ->and($evaluation->results[0]['mode'])->toBe('test')
        ->and(Notification::query()->count())->toBe(0);
});

it('never approves when a test rule would approve', function (): void {
    $friendly = ModerationRule::factory()->approving()->testing()->create();
    $commit = Commit::factory()->pending()->create();

    fakeJev(["rule_{$friendly->id}" => 0.99]);

    $evaluation = resolve(CreateCommitEvaluation::class)->handle($commit);

    expect($commit->refresh()->status)->toBe(CommitStatus::Pending)
        ->and($evaluation)
        ->decision->toBe(EvaluationDecision::Review)
        ->test_decision->toBe(EvaluationDecision::Approved);
});

it('leaves commits a moderator decided on while jev was thinking', function (): void {
    $spam = ModerationRule::factory()->create();
    $commit = Commit::factory()->pending()->create();

    Http::fake(function () use ($commit, $spam) {
        Commit::query()->whereKey($commit->id)->update(['status' => CommitStatus::Approved]);

        return Http::response(jevAnswers(["rule_{$spam->id}" => 0.99]));
    });

    $evaluation = resolve(CreateCommitEvaluation::class)->handle($commit);

    expect($evaluation)->toBeNull()
        ->and($commit->refresh()->status)->toBe(CommitStatus::Approved)
        ->and(CommitEvaluation::query()->count())->toBe(0);
});

it('leaves the commit pending when jev cannot answer', function (Closure $response, string $error): void {
    Exceptions::fake();

    $spam = ModerationRule::factory()->create();
    $commit = Commit::factory()->pending()->create();

    Http::fake(['api.typesafe.ai/*' => $response()]);

    $evaluation = resolve(CreateCommitEvaluation::class)->handle($commit);

    expect($commit->refresh()->status)->toBe(CommitStatus::Pending)
        ->and($evaluation)
        ->decision->toBe(EvaluationDecision::Failed)
        ->test_decision->toBeNull()
        ->results->toBe([])
        ->model->toBeNull()
        ->error->toContain($error);

    Exceptions::assertReportedCount(1);
})->with([
    'a failed connection' => [fn (): Closure => Http::failedConnection(), 'Could not resolve host'],
    'a wrong key' => [fn (): PromiseInterface => Http::response(['detail' => ['error_type' => 'authentication_error']], 401), '401'],
    'a missing answer' => [fn (): PromiseInterface => Http::response(jevAnswers([])), 'Jev did not answer'],
]);

it('does nothing when automatic moderation is off', function (array $config): void {
    config($config);

    ModerationRule::factory()->create();
    $commit = Commit::factory()->pending()->create();

    Http::fake();

    expect(resolve(CreateCommitEvaluation::class)->handle($commit))->toBeNull();

    Http::assertNothingSent();
})->with([
    'disabled' => [['services.typesafe.enabled' => false]],
    'without a key' => [['services.typesafe.key' => '']],
]);

it('does nothing without test or live rules', function (): void {
    ModerationRule::factory()->off()->create();
    $commit = Commit::factory()->pending()->create();

    Http::fake();

    expect(resolve(CreateCommitEvaluation::class)->handle($commit))->toBeNull();

    Http::assertNothingSent();
});

it('does nothing for commits that are no longer pending', function (Closure $commit): void {
    ModerationRule::factory()->create();

    Http::fake();

    expect(resolve(CreateCommitEvaluation::class)->handle($commit()))->toBeNull();

    Http::assertNothingSent();
})->with([
    'approved' => [fn (): Commit => Commit::factory()->create()],
    'rejected' => [fn (): Commit => Commit::factory()->rejected()->create()],
    'deleted' => [fn (): Commit => tap(Commit::factory()->pending()->create())->delete()],
]);

it('judges a reply with its parent and the link it shares', function (): void {
    $harassment = ModerationRule::factory()->create();
    $parent = Commit::factory()->create(['body' => 'Just shipped my first open source package!']);
    $linkPreview = LinkPreview::factory()->create(['url' => 'https://example.com', 'title' => 'Example', 'description' => null, 'site_name' => null]);
    $reply = Commit::factory()->pending()->for($parent, 'parent')->for($linkPreview)->create(['body' => 'Nobody asked. Delete it.']);

    fakeJev(["rule_{$harassment->id}" => 0.96]);

    resolve(CreateCommitEvaluation::class)->handle($reply);

    Http::assertSent(fn (Request $request): bool => $request['state'] === [
        'parent' => ['body' => 'Just shipped my first open source package!'],
        'commit' => ['body' => 'Nobody asked. Delete it.', 'link' => ['url' => 'https://example.com', 'title' => 'Example']],
    ]);
});

it('judges a reply to a deleted commit on its own', function (): void {
    $harassment = ModerationRule::factory()->create();
    $parent = Commit::factory()->create();
    $reply = Commit::factory()->pending()->for($parent, 'parent')->create(['body' => 'Nobody asked.']);
    $parent->delete();

    fakeJev(["rule_{$harassment->id}" => 0.4]);

    resolve(CreateCommitEvaluation::class)->handle($reply->refresh());

    Http::assertSent(fn (Request $request): bool => $request['state'] === ['commit' => ['body' => 'Nobody asked.']]);
});

it('judges every part of a series together and records the decision under its first part', function (float $probability, CommitStatus $status): void {
    $parts = commitSeries(status: CommitStatus::Pending);
    $preview = LinkPreview::factory()->fetched()->create(['url' => 'https://example.com', 'title' => 'Example', 'description' => null, 'site_name' => null]);
    $parts[2]->update(['link_preview_id' => $preview->id]);
    $friendly = ModerationRule::factory()->approving()->create();

    fakeJev(["rule_{$friendly->id}" => $probability]);

    $evaluation = resolve(CreateCommitEvaluation::class)->handle($parts[1]);

    expect($evaluation->commit_id)->toBe($parts[0]->id)
        ->and(Commit::query()->whereKey($parts->modelKeys())->pluck('status')->unique()->all())->toBe([$status])
        ->and(CommitEvaluation::query()->count())->toBe(1)
        ->and(Notification::query()->count())->toBe($status === CommitStatus::Approved ? 1 : 0);

    Http::assertSent(fn (Request $request): bool => $request['state'] === ['commit' => ['parts' => [
        ['body' => $parts[0]->body],
        ['body' => $parts[1]->body],
        ['body' => $parts[2]->body, 'link' => ['url' => 'https://example.com', 'title' => 'Example']],
    ]]]);
})->with([
    'approved' => [0.99, CommitStatus::Approved],
    'uncertain' => [0.1, CommitStatus::Pending],
]);

it('does not evaluate a series that was decided after its pending part was loaded', function (CommitStatus $status): void {
    $parts = commitSeries(status: CommitStatus::Pending);
    $spam = ModerationRule::factory()->create();
    Commit::query()->whereKey($parts->modelKeys())->update(['status' => $status]);

    fakeJev(["rule_{$spam->id}" => 0.99]);

    expect(resolve(CreateCommitEvaluation::class)->handle($parts[1]))->toBeNull()
        ->and($parts->fresh()->pluck('status')->unique()->all())->toBe([$status])
        ->and(CommitEvaluation::query()->count())->toBe(0)
        ->and(Notification::query()->count())->toBe(0);

    Http::assertNothingSent();
})->with([
    'approved' => CommitStatus::Approved,
    'rejected' => CommitStatus::Rejected,
]);

it('does not evaluate a series whose parts were deleted after its pending part was loaded', function (): void {
    $parts = commitSeries(status: CommitStatus::Pending);
    $spam = ModerationRule::factory()->create();
    Commit::query()->whereKey($parts->modelKeys())->delete();

    fakeJev(["rule_{$spam->id}" => 0.99]);

    expect(resolve(CreateCommitEvaluation::class)->handle($parts[1]))->toBeNull()
        ->and(Commit::query()->count())->toBe(0)
        ->and(Commit::withTrashed()->count())->toBe($parts->count())
        ->and(Commit::withTrashed()->pluck('status')->unique()->all())->toBe([CommitStatus::Pending])
        ->and(CommitEvaluation::query()->count())->toBe(0)
        ->and(Notification::query()->count())->toBe(0);

    Http::assertNothingSent();
});

it('rejects the whole series when a later part matches a live reject rule', function (): void {
    $parts = commitSeries(status: CommitStatus::Pending);
    $parts[2]->update(['body' => 'Buy 1,000 GitHub stars for $5']);
    $spam = ModerationRule::factory()->create();

    Http::fake(function (Request $request) use ($spam) {
        $body = $request['state']['commit']['parts'][2]['body'] ?? '';

        return Http::response(jevAnswers(["rule_{$spam->id}" => str_contains($body, 'Buy 1,000') ? 0.99 : 0.01]));
    });

    resolve(CreateCommitEvaluation::class)->handle($parts[0]);

    expect(Commit::query()->whereKey($parts->modelKeys())->pluck('status')->unique()->all())->toBe([CommitStatus::Rejected])
        ->and(Notification::query()->sole())
        ->commit_id->toBe($parts[0]->id)
        ->reason->toBe('This looks like spam or self-promotion.');
});

it('leaves a series pending when jev cannot answer', function (): void {
    Exceptions::fake();
    $parts = commitSeries(status: CommitStatus::Pending);
    ModerationRule::factory()->create();
    Http::fake(['api.typesafe.ai/*' => Http::response(jevAnswers([]))]);

    $evaluation = resolve(CreateCommitEvaluation::class)->handle($parts[0]);

    expect($evaluation->decision)->toBe(EvaluationDecision::Failed)
        ->and($parts->fresh()->pluck('status')->unique()->all())->toBe([CommitStatus::Pending])
        ->and(Notification::query()->count())->toBe(0);
});

it('preserves a moderator decision on a series while jev is thinking', function (): void {
    $parts = commitSeries(status: CommitStatus::Pending);
    $spam = ModerationRule::factory()->create();

    Http::fake(function () use ($parts, $spam) {
        resolve(UpdateCommitStatuses::class)->handle([$parts[2]->id], CommitStatus::Approved);

        return Http::response(jevAnswers(["rule_{$spam->id}" => 0.99]));
    });

    $evaluation = resolve(CreateCommitEvaluation::class)->handle($parts[0]);

    expect($evaluation)->toBeNull()
        ->and($parts->fresh()->pluck('status')->unique()->all())->toBe([CommitStatus::Approved])
        ->and(CommitEvaluation::query()->count())->toBe(0)
        ->and(Notification::query()->sole()->type)->toBe(NotificationType::Approved);
});
