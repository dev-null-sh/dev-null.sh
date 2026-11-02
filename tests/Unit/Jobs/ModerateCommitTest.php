<?php

declare(strict_types=1);

use App\Contracts\Services\ModerationProvider;
use App\Enums\CommitStatus;
use App\Enums\NotificationType;
use App\Exceptions\CommitProcessingFailed;
use App\Jobs\ModerateCommit;
use App\Models\Commit;
use App\Models\CommitImage;
use App\Models\CommitReport;
use App\Models\Notification;
use App\Models\Poll;
use App\Models\User;
use GuzzleHttp\Psr7\Response as Psr7Response;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

it('stores that the ai would approve a commit it does not flag', function (): void {
    $moderation = $this->mock(ModerationProvider::class);
    $moderation->shouldReceive('moderate')->once()->with('Shipped a new feature', [])->andReturn([]);
    $moderation->shouldReceive('isHarmful')->once()->with('Shipped a new feature')->andReturnFalse();

    $commit = Commit::factory()->pending()->create(['body' => 'Shipped a new feature']);

    dispatch_sync(new ModerateCommit($commit));

    expect($commit->refresh())
        ->ai_decision->toBe(CommitStatus::Approved)
        ->ai_categories->toBe([])
        ->status->toBe(CommitStatus::Approved);
});

it('auto-approves clean commits without requiring approved history', function (CommitStatus $previousStatus, bool $sameAuthor, bool $deleted, bool $anonymous, CommitStatus $expected): void {
    $moderation = $this->mock(ModerationProvider::class);
    $moderation->shouldReceive('moderate')->andReturn([]);
    $moderation->shouldReceive('isHarmful')->andReturnFalse();

    $previous = Commit::factory()->create(['status' => $previousStatus]);

    if ($deleted) {
        $previous->delete();
    }

    $attributes = $sameAuthor ? ['user_id' => $previous->user_id] : [];

    if ($anonymous) {
        $attributes['user_id'] = null;
        $previous->update(['user_id' => null]);
    }

    $commit = Commit::factory()->pending()->create($attributes);

    dispatch_sync(new ModerateCommit($commit));

    expect($commit->refresh())
        ->ai_decision->toBe(CommitStatus::Approved)
        ->ai_categories->toBe([])
        ->status->toBe($expected);
})->with([
    'approved history' => [CommitStatus::Approved, true, false, false, CommitStatus::Approved],
    'pending history' => [CommitStatus::Pending, true, false, false, CommitStatus::Approved],
    'rejected history' => [CommitStatus::Rejected, true, false, false, CommitStatus::Approved],
    'another author' => [CommitStatus::Approved, false, false, false, CommitStatus::Approved],
    'deleted history' => [CommitStatus::Approved, true, true, false, CommitStatus::Approved],
    'anonymous author' => [CommitStatus::Approved, true, false, true, CommitStatus::Pending],
]);

it('keeps flagged commits pending even when the author has an approved commit', function (bool $harmful): void {
    $moderation = $this->mock(ModerationProvider::class);
    $moderation->shouldReceive('moderate')->andReturn($harmful ? [] : ['harassment' => 0.9]);
    $moderation->shouldReceive('isHarmful')->andReturn($harmful);

    $previous = Commit::factory()->create();
    $commit = Commit::factory()->pending()->create(['user_id' => $previous->user_id]);

    dispatch_sync(new ModerateCommit($commit));

    expect($commit->refresh())
        ->ai_decision->toBe(CommitStatus::Rejected)
        ->status->toBe(CommitStatus::Pending);
})->with([true, false]);

it('stores that the ai would reject a commit it flags, and why', function (): void {
    $moderation = $this->mock(ModerationProvider::class);
    $moderation->shouldReceive('moderate')->andReturn(['harassment' => 0.9, 'violence' => 0.9]);
    $moderation->shouldReceive('isHarmful')->andReturnFalse();

    $commit = Commit::factory()->pending()->create();

    dispatch_sync(new ModerateCommit($commit));

    expect($commit->refresh())
        ->ai_decision->toBe(CommitStatus::Rejected)
        ->ai_categories->toBe(['harassment', 'violence'])
        ->status->toBe(CommitStatus::Pending);
});

it('rejects a commit the ai is all but certain about, without a reason', function (): void {
    $moderation = $this->mock(ModerationProvider::class);
    $moderation->shouldReceive('moderate')->andReturn(['harassment' => 0.95]);
    $moderation->shouldReceive('isHarmful')->andReturnFalse();

    $commit = Commit::factory()->pending()->create();

    dispatch_sync(new ModerateCommit($commit));

    expect($commit->refresh())
        ->ai_decision->toBe(CommitStatus::Rejected)
        ->ai_categories->toBe(['harassment'])
        ->status->toBe(CommitStatus::Rejected);

    $notification = Notification::query()->where('commit_id', $commit->id)->sole();

    expect($notification)
        ->type->toBe(NotificationType::Rejected)
        ->reason->toBeNull();
});

it('leaves a commit the ai is not all but certain about to moderators', function (): void {
    $moderation = $this->mock(ModerationProvider::class);
    $moderation->shouldReceive('moderate')->andReturn(['harassment' => 0.94]);
    $moderation->shouldReceive('isHarmful')->andReturnFalse();

    $commit = Commit::factory()->pending()->create();

    dispatch_sync(new ModerateCommit($commit));

    expect($commit->refresh())
        ->ai_decision->toBe(CommitStatus::Rejected)
        ->status->toBe(CommitStatus::Pending);
});

it('checks the images of a commit too', function (string $category): void {
    Storage::fake();
    Storage::put('commit-images/first.webp', 'first');
    Storage::put('commit-images/second.webp', 'second');

    $moderation = $this->mock(ModerationProvider::class);
    $moderation->shouldReceive('moderate')->once()->with('Look at this', [
        'data:image/webp;base64,'.base64_encode('first'),
        'data:image/webp;base64,'.base64_encode('second'),
    ])->andReturn([$category => 0.9]);
    $moderation->shouldReceive('isHarmful')->once()->with('Look at this')->andReturnFalse();

    $commit = Commit::factory()->pending()->create(['body' => 'Look at this']);
    CommitImage::factory()->for($commit)->create(['path' => 'commit-images/first.webp', 'position' => 0]);
    CommitImage::factory()->for($commit)->create(['path' => 'commit-images/second.webp', 'position' => 1]);

    dispatch_sync(new ModerateCommit($commit));

    expect($commit->refresh())
        ->ai_decision->toBe(CommitStatus::Rejected)
        ->ai_categories->toBe([$category])
        ->status->toBe(CommitStatus::Pending);
})->with(['sexual', 'sexual/minors']);

it('checks the image descriptions and poll options along with the body', function (): void {
    Storage::fake();
    Storage::put('commit-images/first.webp', 'first');

    $moderation = $this->mock(ModerationProvider::class);
    $moderation->shouldReceive('moderate')->once()->with("Which editor?\n\nA screenshot\n\nVim\n\nEmacs", ['data:image/webp;base64,'.base64_encode('first')])->andReturn([]);
    $moderation->shouldReceive('isHarmful')->once()->with("Which editor?\n\nA screenshot\n\nVim\n\nEmacs")->andReturnFalse();

    $commit = Commit::factory()->pending()->create(['body' => 'Which editor?']);
    CommitImage::factory()->for($commit)->create(['path' => 'commit-images/first.webp', 'position' => 0, 'description' => 'A screenshot']);
    Poll::factory()->for($commit)->unopened()->withOptions('Vim', 'Emacs')->create();

    dispatch_sync(new ModerateCommit($commit));
});

it('keeps a commit pending when only its image descriptions or poll options are flagged', function (string $where): void {
    Storage::fake();
    Storage::put('commit-images/first.webp', 'first');

    $moderation = $this->mock(ModerationProvider::class);
    $moderation->shouldReceive('moderate')->andReturnUsing(fn (string $text): array => str_contains($text, 'buy my course') ? ['harassment' => 0.9] : []);
    $moderation->shouldReceive('isHarmful')->andReturnFalse();

    $commit = Commit::factory()->pending()->create(['body' => 'Which editor?']);
    CommitImage::factory()->for($commit)->create([
        'path' => 'commit-images/first.webp',
        'position' => 0,
        'description' => $where === 'image description' ? 'buy my course' : null,
    ]);
    Poll::factory()->for($commit)->unopened()->withOptions(...$where === 'poll option' ? ['buy my course', 'Emacs'] : ['Vim', 'Emacs'])->create();

    dispatch_sync(new ModerateCommit($commit));

    expect($commit->refresh())
        ->ai_decision->toBe(CommitStatus::Rejected)
        ->ai_categories->toBe(['harassment'])
        ->status->toBe(CommitStatus::Pending);
})->with(['image description', 'poll option']);

it('checks the image description of a commit without a body as its text', function (): void {
    Storage::fake();
    Storage::put('commit-images/first.webp', 'first');

    $moderation = $this->mock(ModerationProvider::class);
    $moderation->shouldReceive('moderate')->once()->with('A screenshot', ['data:image/webp;base64,'.base64_encode('first')])->andReturn([]);
    $moderation->shouldReceive('isHarmful')->once()->with('A screenshot')->andReturnFalse();

    $commit = Commit::factory()->pending()->create(['body' => '']);
    CommitImage::factory()->for($commit)->create(['path' => 'commit-images/first.webp', 'position' => 0, 'description' => 'A screenshot']);

    dispatch_sync(new ModerateCommit($commit));
});

it('checks only the images of a commit without a body', function (): void {
    Storage::fake();
    Storage::put('commit-images/first.webp', 'first');

    $moderation = $this->mock(ModerationProvider::class);
    $moderation->shouldReceive('moderate')->once()->with('', ['data:image/webp;base64,'.base64_encode('first')])->andReturn([]);
    $moderation->shouldNotReceive('isHarmful');

    $commit = Commit::factory()->pending()->create(['body' => '']);
    CommitImage::factory()->for($commit)->create(['path' => 'commit-images/first.webp', 'position' => 0]);

    dispatch_sync(new ModerateCommit($commit));

    expect($commit->refresh())
        ->ai_decision->toBe(CommitStatus::Approved)
        ->ai_categories->toBe([]);
});

it('flags text the moderation model misses, such as an implied threat, without rejecting it', function (): void {
    $moderation = $this->mock(ModerationProvider::class);
    $moderation->shouldReceive('moderate')->andReturn([]);
    $moderation->shouldReceive('isHarmful')->once()->with('I hate joomla and i have a bomb')->andReturnTrue();

    $commit = Commit::factory()->pending()->create(['body' => 'I hate joomla and i have a bomb']);

    dispatch_sync(new ModerateCommit($commit));

    expect($commit->refresh())
        ->ai_decision->toBe(CommitStatus::Rejected)
        ->ai_categories->toBe(['harmful-text'])
        ->status->toBe(CommitStatus::Pending);
});

it('skips commits that were moderated in the meantime', function (): void {
    $moderation = $this->mock(ModerationProvider::class);
    $moderation->shouldNotReceive('moderate');
    $moderation->shouldNotReceive('isHarmful');

    $commit = Commit::factory()->create();

    dispatch_sync(new ModerateCommit($commit));

    expect($commit->refresh()->ai_decision)->toBeNull();
});

it('approves queued commits without calling the ai when the author has good reputation', function (): void {
    $moderation = $this->mock(ModerationProvider::class);
    $moderation->shouldNotReceive('moderate');
    $moderation->shouldNotReceive('isHarmful');

    $user = User::factory()->create(['created_at' => now()->subHour()->subSecond()]);
    Commit::factory()->for($user)->create();
    $commit = Commit::factory()->for($user)->pending()->create();
    followersOf($user, 10);

    dispatch_sync(new ModerateCommit($commit));

    expect($commit->refresh())
        ->status->toBe(CommitStatus::Approved)
        ->ai_decision->toBeNull();
});

it('skips commits that were deleted in the meantime', function (): void {
    $moderation = $this->mock(ModerationProvider::class);
    $moderation->shouldNotReceive('moderate');
    $moderation->shouldNotReceive('isHarmful');

    $commit = Commit::factory()->pending()->create();
    $commit->delete();

    dispatch_sync(new ModerateCommit($commit));
});

it('throws when the ai is unavailable, so the job is retried', function (): void {
    $moderation = $this->mock(ModerationProvider::class);
    $moderation->shouldReceive('moderate')->andThrow(new RequestException(new Response(new Psr7Response(503, [], '{"error":"Sensitive provider response"}'))));

    $commit = Commit::factory()->pending()->create();

    expect(fn () => dispatch_sync(new ModerateCommit($commit)))->toThrow(function (CommitProcessingFailed $exception) use ($commit): void {
        expect($exception->getMessage())->toBe('Moderation failed for commit '.$commit->id.' ('.RequestException::class.', code 503).')
            ->and($exception->getCode())->toBe(503)
            ->and($exception->getPrevious())->toBeNull();
    })->and($commit->refresh())->ai_decision->toBeNull()->status->toBe(CommitStatus::Pending);
});

it('keeps unavailable moderation pending and recovers on a later queued attempt', function (bool $recovers): void {
    config(['queue.default' => 'sync']);
    Exceptions::fake();
    $unavailable = fn (): never => throw new RequestException(new Response(new Psr7Response(503, [], '{"error":"Sensitive provider response"}')));
    $moderation = $this->mock(ModerationProvider::class);
    $moderation->shouldReceive('moderate')->times(3)->andReturnUsing($unavailable, $unavailable, $recovers ? fn (): array => [] : $unavailable);
    $moderation->shouldReceive('isHarmful')->andReturnFalse();
    $commit = Commit::factory()->pending()->create();
    Queue::connection('database')->push(new ModerateCommit($commit));

    expect(json_decode((string) DB::table('jobs')->value('payload'), true)['timeout'])->toBe(180);

    foreach ([0, 60, 600] as $delay) {
        $this->travel($delay)->seconds();
        $this->artisan('queue:work', ['connection' => 'database', '--once' => true])->assertSuccessful();

        if ($delay !== 600) {
            expect($commit->refresh())->status->toBe(CommitStatus::Pending)->ai_decision->toBeNull();
        }
    }

    expect($commit->refresh())->status->toBe($recovers ? CommitStatus::Approved : CommitStatus::Pending)
        ->ai_decision->toBe($recovers ? CommitStatus::Approved : null);
    $this->assertDatabaseCount('jobs', 0);
    $this->assertDatabaseCount('failed_jobs', $recovers ? 0 : 1);

    if (! $recovers) {
        expect(DB::table('failed_jobs')->value('exception'))->toContain('Moderation failed for commit '.$commit->id)
            ->not->toContain('Sensitive provider response');
    }
})->with([true, false]);

it('approves clean commits even when their author has been reported', function (): void {
    $moderation = $this->mock(ModerationProvider::class);
    $moderation->shouldReceive('moderate')->andReturn([]);
    $moderation->shouldReceive('isHarmful')->andReturnFalse();
    $reported = Commit::factory()->create();
    CommitReport::factory()->for($reported)->create();
    $commit = Commit::factory()->pending()->create(['user_id' => $reported->user_id]);

    dispatch_sync(new ModerateCommit($commit));

    expect($commit->refresh())
        ->ai_decision->toBe(CommitStatus::Approved)
        ->status->toBe(CommitStatus::Approved);
});

it('approves reputable authors without calling the ai even when they have been reported', function (): void {
    $moderation = $this->mock(ModerationProvider::class);
    $moderation->shouldNotReceive('moderate');
    $moderation->shouldNotReceive('isHarmful');

    $user = User::factory()->create(['created_at' => now()->subHours(2)]);
    $reported = Commit::factory()->for($user)->create();
    followersOf($user, 10);
    $commit = Commit::factory()->for($user)->pending()->create();
    CommitReport::factory()->for($reported)->create();

    dispatch_sync(new ModerateCommit($commit));

    expect($commit->refresh())
        ->status->toBe(CommitStatus::Approved)
        ->ai_decision->toBeNull();
});
