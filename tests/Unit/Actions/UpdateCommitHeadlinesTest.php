<?php

declare(strict_types=1);

use App\Actions\UpdateCommitHeadlines;
use App\Enums\CommitStatus;
use App\Models\Commit;
use App\Models\Headline;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;

it('leaves unsuitable root commits without a headline', function (): void {
    $commit = Commit::factory()->create(['body' => 'What a sunny day.']);

    resolve(UpdateCommitHeadlines::class)->handle($commit, [], hash('sha256', $commit->body));
    $classifiedAt = $commit->refresh()->headlines_classified_at;
    $this->travel(1)->days();
    resolve(UpdateCommitHeadlines::class)->handle($commit, ['Late duplicate result'], hash('sha256', $commit->body));

    expect($commit->headlines()->count())->toBe(0)
        ->and($commit->refresh()->headlines_classified_at?->equalTo($classifiedAt))->toBeTrue()
        ->and(Headline::query()->count())->toBe(0);
});

it('links all matching existing headlines without generating a new one', function (): void {
    $commit = Commit::factory()->create(['body' => 'Trying pgvector for semantic search.']);
    $headlines = Headline::factory()->count(3)->sequence(['name' => 'Vector search'], ['name' => 'Postgres extensions'], ['name' => 'Laravel releases'])->create();

    resolve(UpdateCommitHeadlines::class)->handle($commit, ['Vector search', 'Postgres extensions'], hash('sha256', $commit->body));

    expect($commit->headlines()->pluck('name')->all())->toEqualCanonicalizing(['Vector search', 'Postgres extensions'])
        ->and(Headline::query()->count())->toBe(3)
        ->and($headlines[2]->commits()->count())->toBe(0);
});

it('creates returned headlines or reuses an identical name', function (bool $exists): void {
    $commit = Commit::factory()->create(['body' => 'What database are you using for vector search?']);
    if ($exists) {
        Headline::factory()->create(['name' => 'Vector search databases']);
    }

    resolve(UpdateCommitHeadlines::class)->handle($commit, ['Vector search databases'], hash('sha256', $commit->body));

    expect($commit->headlines()->sole()->name)->toBe('Vector search databases')
        ->and(Headline::query()->count())->toBe(1);
})->with([false, true]);

it('does not link replies, unavailable commits, or commits already assigned', function (string $state): void {
    $commit = Commit::factory()->create();
    match ($state) {
        'reply' => $commit->update(['parent_id' => Commit::factory()->create()->id]),
        'pending' => $commit->update(['status' => CommitStatus::Pending]),
        'rejected' => $commit->update(['status' => CommitStatus::Rejected]),
        'empty' => $commit->update(['body' => '']),
        'deleted' => $commit->delete(),
        'assigned' => $commit->headlines()->attach(Headline::factory()->create()),
    };

    resolve(UpdateCommitHeadlines::class)->handle($commit, ['Unwanted headline'], hash('sha256', $commit->body));

    expect($commit->headlines()->count())->toBe($state === 'assigned' ? 1 : 0);
})->with(['reply', 'pending', 'rejected', 'empty', 'deleted', 'assigned']);

it('does not attach stale provider results after deletion, rejection or another worker', function (string $change): void {
    $commit = Commit::factory()->create();
    $hash = hash('sha256', $commit->body);
    match ($change) {
        'deleted' => $commit->delete(),
        'erased' => $commit->update(['body' => '']),
        'edited' => $commit->update(['body' => 'Different subject']),
        'rejected' => $commit->update(['status' => CommitStatus::Rejected]),
        'assigned' => $commit->headlines()->attach(Headline::factory()->create(['name' => 'Concurrent headline'])),
    };

    resolve(UpdateCommitHeadlines::class)->handle($commit, ['Stale headline'], $hash);

    expect(Headline::query()->where('name', 'Stale headline')->exists())->toBeFalse()
        ->and($commit->headlines()->pluck('name')->all())->toBe($change === 'assigned' ? ['Concurrent headline'] : []);
})->with(['deleted', 'erased', 'edited', 'rejected', 'assigned']);

it('keeps quoted context public until its headline link is written', function (): void {
    $connection = DB::build([...DB::connection()->getConfig(), 'name' => 'headline-concurrency']);
    $original = Commit::factory()->make(['id' => (string) Str::uuid7(), 'user_id' => null, 'body' => 'The /dev/null iOS beta is open.']);
    $quote = Commit::factory()->make(['id' => (string) Str::uuid7(), 'user_id' => null, 'body' => 'Go test this app!', 'quoted_commit_id' => $original->id]);
    $this->beforeApplicationDestroyed(function () use ($connection, $original, $quote): void {
        $connection->table('commits')->whereIn('id', [$original->id, $quote->id])->delete();
        $connection->disconnect();
    });
    $connection->table('commits')->insert($original->getAttributes());
    $connection->table('commits')->insert($quote->getAttributes());
    $connection->statement("set lock_timeout = '50ms'");
    $blocked = false;
    Event::listen('eloquent.creating: '.Headline::class, function () use ($connection, $original, &$blocked): void {
        try {
            $connection->table('commits')->where('id', $original->id)->update(['status' => CommitStatus::Rejected->value]);
        } catch (QueryException $queryException) {
            expect($queryException->getCode())->toBe('55P03');
            $blocked = true;
        }
    });

    resolve(UpdateCommitHeadlines::class)->handle($quote, ['/dev/null iOS app beta'], hash('sha256', $quote->headlineBody()));

    expect($blocked)->toBeTrue()
        ->and($original->refresh()->status)->toBe(CommitStatus::Approved)
        ->and($quote->headlines()->sole()->name)->toBe('/dev/null iOS app beta');
});
