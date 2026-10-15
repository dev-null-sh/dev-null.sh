<?php

declare(strict_types=1);

use App\Enums\Log;
use App\Enums\PollDuration;
use App\Models\Bookmark;
use App\Models\Commit;
use App\Models\Poll;
use App\Models\PollVote;
use App\Models\User;
use Illuminate\Support\Facades\DB;

it('may attach a poll to a commit', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->fromRoute('log.show', Log::Following)
        ->post(route('commit.store'), [
            'body' => 'Tabs or spaces?',
            'poll' => ['options' => ['Tabs', ' Spaces '], 'duration' => PollDuration::SevenDays->value],
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirectToRoute('log.show', Log::Following);

    $poll = $user->commits()->sole()->poll;

    expect($poll->duration)->toBe(PollDuration::SevenDays)
        ->and($poll->options->pluck('label')->all())->toBe(['Tabs', 'Spaces']);
});

it('validates the poll', function (array $poll, array|string $errors): void {
    $this->actingAs(User::factory()->create())
        ->fromRoute('log.show', Log::Following)
        ->post(route('commit.store'), ['body' => 'Tabs or spaces?', 'poll' => $poll])
        ->assertSessionHasErrors($errors);

    expect(Commit::query()->count())->toBe(0);
})->with([
    'one option' => [['options' => ['Tabs'], 'duration' => 1440], 'poll.options'],
    'five options' => [['options' => ['A', 'B', 'C', 'D', 'E'], 'duration' => 1440], 'poll.options'],
    'empty option' => [['options' => ['Tabs', '  '], 'duration' => 1440], 'poll.options.1'],
    'long option' => [['options' => ['Tabs', str_repeat('a', 51)], 'duration' => 1440], 'poll.options.1'],
    'duplicate options' => [['options' => ['Tabs', 'tabs'], 'duration' => 1440], 'poll.options.1'],
    'unknown duration' => [['options' => ['Tabs', 'Spaces'], 'duration' => 30], 'poll.duration'],
    'no duration' => [['options' => ['Tabs', 'Spaces']], 'poll.duration'],
    'no options' => [['duration' => 1440], 'poll.options'],
]);

it('may not attach a poll to a reply', function (): void {
    $parent = Commit::factory()->create();

    $this->actingAs(User::factory()->create())
        ->fromRoute('log.show', Log::Everyone)
        ->post(route('commit.store'), [
            'body' => 'Tabs or spaces?',
            'parent_id' => $parent->id,
            'poll' => ['options' => ['Tabs', 'Spaces'], 'duration' => 1440],
        ])
        ->assertSessionHasErrors('poll');

    expect($parent->replies()->count())->toBe(0);
});

it('shows polls in the logs without the results before voting', function (Log $log): void {
    $user = User::factory()->create();
    $poll = Poll::factory()->withOptions('Tabs', 'Spaces')->create();
    $user->following()->attach($poll->commit->user);
    PollVote::factory()->for($poll->options[0], 'option')->create();

    $this->actingAs($user)
        ->get(route('log.show', $log))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('commits.data.0.poll.has_voted', false)
            ->where('commits.data.0.poll.total_votes', null)
            ->where('commits.data.0.poll.options.0.label', 'Tabs')
            ->where('commits.data.0.poll.options.0.votes_count', null));
})->with(Log::cases());

it('shows the poll of a commit and of its ancestor', function (): void {
    $user = User::factory()->create();
    $poll = Poll::factory()->withOptions('Tabs', 'Spaces')->create();
    $reply = Commit::factory()->for($poll->commit, 'parent')->create();
    PollVote::factory()->for($poll->options[1], 'option')->for($user)->create();

    $this->actingAs($user)
        ->get(route('commit.show', [$poll->commit->user, $poll->commit]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('commit.poll.total_votes', 1)
            ->where('commit.poll.options.1.voted', true));

    $this->get(route('commit.show', [$reply->user, $reply]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('commit.poll', null)
            ->where('ancestors.0.poll.options.1.voted', true));
});

it('shows polls on profiles and saved commits', function (): void {
    $user = User::factory()->create();
    $author = User::factory()->create();
    $pinned = Poll::factory()->for(Commit::factory()->for($author))->withOptions('Tabs', 'Spaces')->create();
    $latest = Poll::factory()->for(Commit::factory()->for($author))->withOptions('Vim', 'Emacs')->create();
    $author->update(['pinned_commit_id' => $pinned->commit_id]);
    Bookmark::factory()->create(['user_id' => $user->id, 'commit_id' => $latest->commit_id]);

    $this->actingAs($user)
        ->get(route('user.show', $author))
        ->assertInertia(fn ($page) => $page
            ->where('pinnedCommit.poll.options.0.label', 'Tabs')
            ->where('commits.data.0.poll.options.0.label', 'Vim'));

    $this->get(route('bookmark.index'))
        ->assertInertia(fn ($page) => $page->where('commits.data.0.poll.options.0.label', 'Vim'));
});

it('shows the poll of a pending commit to moderators', function (): void {
    config(['app.admins' => ['admin']]);
    Poll::factory()->for(Commit::factory()->pending())->withOptions('Tabs', 'Spaces')->unopened()->create();

    $this->actingAs(User::factory()->create(['username' => 'admin']))
        ->get(route('moderation.show'))
        ->assertInertia(fn ($page) => $page
            ->where('commits.data.0.poll.closes_at', null)
            ->where('commits.data.0.poll.options.1.label', 'Spaces'));
});

it('loads the polls of the log with a bounded number of queries', function (): void {
    $user = User::factory()->create();
    Poll::factory()->withOptions('Tabs', 'Spaces')->create();
    $this->actingAs($user);
    DB::enableQueryLog();

    $this->get(route('log.show', Log::Everyone))->assertOk();
    $singleCount = count(DB::getQueryLog());
    Poll::factory()->count(19)->withOptions('Vim', 'Emacs', 'Nano')->create();
    PollVote::factory()->count(5)->create();
    DB::flushQueryLog();

    $this->get(route('log.show', Log::Everyone))->assertOk()->assertInertia(fn ($page) => $page->has('commits.data', 20));
    expect(count(DB::getQueryLog()))->toBe($singleCount);
    DB::disableQueryLog();
});

it('does not show polls to guests', function (): void {
    $poll = Poll::factory()->withOptions('Secret option A', 'Secret option B')->closed()->create();

    $this->get(route('commit.show', [$poll->commit->user, $poll->commit]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('welcome')->missing('commit'))
        ->assertDontSee('Secret option A');
});
