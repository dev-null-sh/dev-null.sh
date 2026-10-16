<?php

declare(strict_types=1);

use App\Actions\CreateCommitSeries;
use App\Enums\CommitStatus;
use App\Jobs\EvaluateCommitJob;
use App\Jobs\UnfurlLink;
use App\Models\Commit;
use App\Models\ModerationRule;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

it('chains each part as a reply to the part before it', function (): void {
    $user = User::factory()->create();

    $parts = resolve(CreateCommitSeries::class)->handle($user, ['First part', 'Second part', 'Third part']);

    expect($parts)->toHaveCount(3)
        ->and($parts->pluck('body')->all())->toBe(['First part', 'Second part', 'Third part'])
        ->and($parts->pluck('parent_id')->all())->toBe([null, $parts[0]->id, $parts[1]->id])
        ->and($parts->pluck('series_position')->all())->toBe([1, 2, 3])
        ->and($parts->pluck('series_size')->unique()->all())->toBe([3])
        ->and($parts->pluck('series_id')->unique())->toHaveCount(1)
        ->and($parts[0]->series_id)->toBeString()
        ->and($parts->every(fn (Commit $part): bool => $part->user_id === $user->id))->toBeTrue()
        ->and(Commit::query()->count())->toBe(3);
});

it('creates a series pending review', function (): void {
    $parts = resolve(CreateCommitSeries::class)->handle(User::factory()->create(), ['First part', 'Second part']);

    expect($parts->pluck('status')->unique()->all())->toBe([CommitStatus::Pending]);
});

it('approves a series of an admin right away without notifying them about their own parts', function (): void {
    config(['app.admins' => ['nunomaduro']]);

    $parts = resolve(CreateCommitSeries::class)->handle(User::factory()->create(['username' => 'nunomaduro']), ['First part', 'Second part']);

    expect($parts->pluck('status')->unique()->all())->toBe([CommitStatus::Approved])
        ->and(Notification::query()->count())->toBe(0);
});

it('stores emoji shortcodes and attaches a link preview for each part', function (): void {
    Queue::fake();

    $parts = resolve(CreateCommitSeries::class)->handle(User::factory()->create(), [
        'shipped it :heart :rocket:',
        'notes at https://example.com/notes',
    ]);

    expect($parts[0]->body)->toBe('shipped it ❤️ 🚀')
        ->and($parts[0]->link_preview_id)->toBeNull()
        ->and($parts[1]->linkPreview?->url)->toBe('https://example.com/notes');

    Queue::assertPushed(UnfurlLink::class, 1);
});

it('evaluates a series once after fetching every link preview', function (): void {
    Queue::fake();
    config(['services.typesafe.key' => 'test-key', 'services.typesafe.enabled' => true]);
    ModerationRule::factory()->create();

    resolve(CreateCommitSeries::class)->handle(User::factory()->create(), [
        'First part at https://example.com/first',
        'Second part at https://example.com/second',
    ]);

    Queue::assertPushed(UnfurlLink::class, 1);
    Queue::assertPushedWithChain(UnfurlLink::class, [UnfurlLink::class, EvaluateCommitJob::class]);
    Queue::assertNotPushed(EvaluateCommitJob::class);
});

it('evaluates a series without links under its first part', function (): void {
    Queue::fake();
    config(['services.typesafe.key' => 'test-key', 'services.typesafe.enabled' => true]);
    ModerationRule::factory()->create();

    $parts = resolve(CreateCommitSeries::class)->handle(User::factory()->create(), ['First part', 'Second part']);

    Queue::assertPushed(EvaluateCommitJob::class, 1);
    Queue::assertPushed(EvaluateCommitJob::class, fn (EvaluateCommitJob $job): bool => $job->commit->is($parts[0]));
});

it('moderates a newly created series only after all its parts are saved', function (): void {
    config(['services.typesafe.key' => 'test-key', 'services.typesafe.enabled' => true]);
    $spam = ModerationRule::factory()->create();

    Http::fake(function (Request $request) use ($spam) {
        expect(Commit::query()->count())->toBe(2)
            ->and($request['state']['commit']['parts'][1]['body'])->toBe('Buy 1,000 GitHub stars for $5');

        return Http::response(jevAnswers(["rule_{$spam->id}" => 0.99]));
    });

    $parts = resolve(CreateCommitSeries::class)->handle(User::factory()->create(), ['Shipped it', 'Buy 1,000 GitHub stars for $5']);

    expect($parts->fresh()->pluck('status')->unique()->all())->toBe([CommitStatus::Rejected])
        ->and(Notification::query()->sole()->commit_id)->toBe($parts[0]->id);

    Http::assertSentCount(1);
});

it('does not evaluate a series when automatic moderation does not apply', function (array $settings, string $username): void {
    Queue::fake();
    config(['services.typesafe.key' => 'test-key', 'services.typesafe.enabled' => true, 'app.admins' => ['admin']]);
    config($settings);
    ModerationRule::factory()->create();

    resolve(CreateCommitSeries::class)->handle(User::factory()->create(['username' => $username]), ['First part', 'Second part']);

    Queue::assertNotPushed(EvaluateCommitJob::class);
})->with([
    'disabled' => [['services.typesafe.enabled' => false], 'octocat'],
    'without a key' => [['services.typesafe.key' => ''], 'octocat'],
    'for admins' => [[], 'admin'],
]);

it('evaluates a series after fetching only the link cards its author chose', function (): void {
    Queue::fake();
    config(['services.typesafe.key' => 'test-key', 'services.typesafe.enabled' => true]);
    ModerationRule::factory()->create();

    $parts = resolve(CreateCommitSeries::class)->handle(User::factory()->create(), [
        'First part at https://example.com/first and https://example.com/chosen',
        'Second part at https://example.com/omitted',
        'Third part at https://example.com/third',
    ], linkPreviews: [0 => 'https://example.com/chosen', 1 => false]);

    expect($parts[0]->linkPreview?->url)->toBe('https://example.com/chosen')
        ->and($parts[1]->link_preview_id)->toBeNull()
        ->and($parts[2]->linkPreview?->url)->toBe('https://example.com/third');

    Queue::assertPushedWithChain(UnfurlLink::class, [
        new UnfurlLink($parts[2]->linkPreview),
        new EvaluateCommitJob($parts[0]),
    ], fn (UnfurlLink $job): bool => $job->linkPreview->is($parts[0]->linkPreview));
    Queue::assertPushed(UnfurlLink::class, 1);
    Queue::assertNotPushed(EvaluateCommitJob::class);
});
