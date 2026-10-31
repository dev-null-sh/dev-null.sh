<?php

declare(strict_types=1);

use App\Actions\CreateCommit;
use App\Actions\UpdateCommitHeadlines;
use App\Actions\UpdateCommitStatuses;
use App\Contracts\Services\HeadlineProvider;
use App\Enums\CommitStatus;
use App\Exceptions\CommitProcessingFailed;
use App\Jobs\LinkCommitHeadlines;
use App\Jobs\ProcessCommitHeadlines;
use App\Models\Commit;
use App\Models\Headline;
use App\Models\User;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Pest\Expectation;

it('queues headlines only for approved nonempty root commits when posted or approved later', function (): void {
    config(['app.admins' => ['admin']]);
    Queue::fake([ProcessCommitHeadlines::class]);
    $admin = User::factory()->create(['handle' => 'admin']);
    $action = resolve(CreateCommit::class);
    $root = $action->handle($admin, 'Trying pgvector.');
    $action->handle($admin, 'Trying pgvector too.', parent: $root);
    $action->handle($admin, '');

    $pending = $action->handle(User::factory()->create(), 'New Laravel features');
    $reply = Commit::factory()->pending()->for($root, 'parent')->create();

    Queue::assertPushed(ProcessCommitHeadlines::class, 1);
    Queue::assertPushed(ProcessCommitHeadlines::class, fn (ProcessCommitHeadlines $job): bool => $job->commitIds === [$root->id] && $job->afterCommit === true);

    resolve(UpdateCommitStatuses::class)->handle([$pending->id, $reply->id], CommitStatus::Approved);

    Queue::assertPushed(ProcessCommitHeadlines::class, 2);
    Queue::assertPushed(ProcessCommitHeadlines::class, fn (ProcessCommitHeadlines $job): bool => $job->commitIds === [$pending->id]);
});

it('assigns a headline once even when the job runs twice', function (): void {
    $commit = Commit::factory()->create(['body' => 'Trying pgvector.']);
    $job = new LinkCommitHeadlines($commit, ['Vector databases'], hash('sha256', $commit->body));

    $job->handle(resolve(UpdateCommitHeadlines::class));
    $this->travel(1)->days();
    $job->handle(resolve(UpdateCommitHeadlines::class));

    expect($commit->headlines()->sole()->name)->toBe('Vector databases')
        ->and($commit->headlines()->sole()->getRelation('pivot')->created_at->toDateTimeString())->toBe(now()->subDay()->toDateTimeString())
        ->and(Headline::query()->count())->toBe(1);
});

it('retries failures without logging commit or provider content', function (): void {
    $commit = Commit::factory()->create(['body' => 'Private test body']);
    $this->mock(HeadlineProvider::class)->shouldReceive('candidates')->andThrow(new RuntimeException('Sensitive provider response'));

    expect(fn () => new ProcessCommitHeadlines([$commit->id])->handle(resolve(HeadlineProvider::class)))
        ->toThrow(fn (CommitProcessingFailed $error): Expectation => expect($error->getMessage())->toContain($commit->id)->not->toContain('Private test body', 'Sensitive provider response')
            ->and($error->getPrevious())->toBeNull());
    expect(Headline::query()->count())->toBe(0)
        ->and($commit->headlines()->count())->toBe(0);
});

it('rolls back failed links without exposing content in the job error', function (): void {
    $commit = Commit::factory()->create(['body' => 'Private test body']);
    Event::listen('eloquent.creating: '.Headline::class, function (): never {
        throw new RuntimeException('Sensitive database response');
    });

    expect(fn (): ?int => new LinkCommitHeadlines($commit, ['Vector databases'], hash('sha256', $commit->body))->handle(resolve(UpdateCommitHeadlines::class)))
        ->toThrow(fn (CommitProcessingFailed $error): Expectation => expect($error->getMessage())->toContain($commit->id)->not->toContain('Private test body', 'Sensitive database response')
            ->and($error->getPrevious())->toBeNull());
    expect($commit->headlines()->count())->toBe(0);
});
