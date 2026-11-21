<?php

declare(strict_types=1);

use App\Models\Commit;
use App\Models\User;
use App\Queries\SuggestedUsersQuery;
use Illuminate\Database\Eloquent\Collection;

/**
 * @return Collection<int, User>
 */
function vouchedFor(User $user, int $count): Collection
{
    $friend = User::factory()->create();
    $user->following()->attach($friend);

    $vouched = User::factory()->count($count)->has(Commit::factory())->create();
    $friend->following()->attach($vouched);

    return $vouched;
}

/**
 * @return Collection<int, User>
 */
function popularUsers(int $count): Collection
{
    return User::factory()->count($count)->has(Commit::factory())->create()
        ->each(fn (User $popular) => $popular->followers()->attach(User::factory()->count(2)->create()));
}

it('suggests people with commits the user does not follow yet', function (): void {
    $user = User::factory()->has(Commit::factory())->create();
    $followed = User::factory()->has(Commit::factory())->create();
    $stranger = User::factory()->has(Commit::factory())->create();

    $user->following()->attach($followed);

    $suggestions = resolve(SuggestedUsersQuery::class)->get($user);

    expect($suggestions->pluck('id')->all())->toBe([$stranger->id]);
});

it('skips people without approved commits', function (): void {
    $user = User::factory()->create();

    User::factory()->create();
    User::factory()->has(Commit::factory()->pending())->create();
    User::factory()->has(Commit::factory()->rejected())->create();

    expect(resolve(SuggestedUsersQuery::class)->get($user))->toBeEmpty();
});

it('skips people who only replied', function (): void {
    $user = User::factory()->create();

    $parent = Commit::factory()->for($user)->create();
    User::factory()->has(Commit::factory()->for($parent, 'parent'))->create();

    expect(resolve(SuggestedUsersQuery::class)->get($user))->toBeEmpty();
});

it('picks the people most followed by the user follows', function (): void {
    $user = User::factory()->create();
    [$friendA, $friendB] = User::factory()->count(2)->create();

    $user->following()->attach([$friendA->id, $friendB->id]);

    $followedByOneFriend = User::factory()->has(Commit::factory())->create();
    $followedByBothFriends = User::factory()->has(Commit::factory())->create();

    $followedByOneFriend->followers()->attach($friendA);
    $followedByBothFriends->followers()->attach([$friendA->id, $friendB->id]);

    $suggestions = resolve(SuggestedUsersQuery::class)->get($user, limit: 1);

    expect($suggestions->pluck('id')->all())->toBe([$followedByBothFriends->id])
        ->and($suggestions->first()->followed_by_following_count)->toBe(2);
});

it('mixes one or two popular people with people the user might know', function (): void {
    $user = User::factory()->create();
    $network = vouchedFor($user, 5);
    $popular = popularUsers(5);

    $suggestions = resolve(SuggestedUsersQuery::class)->get($user);
    $popularCount = $suggestions->filter(fn (User $suggested): bool => $popular->contains($suggested))->count();

    expect($suggestions)->toHaveCount(3)
        ->and($popularCount)->toBeIn([1, 2])
        ->and($suggestions->filter(fn (User $suggested): bool => $network->contains($suggested)))->toHaveCount(3 - $popularCount);
});

it('suggests only popular people when the user might know nobody', function (): void {
    $user = User::factory()->create();
    $popular = popularUsers(4);

    $suggestions = resolve(SuggestedUsersQuery::class)->get($user);

    expect($suggestions)->toHaveCount(3)
        ->and($suggestions->pluck('id')->all())->each->toBeIn($popular->modelKeys());
});

it('suggests only people the user might know when nobody else is around', function (): void {
    $user = User::factory()->create();
    $network = vouchedFor($user, 4);

    $suggestions = resolve(SuggestedUsersQuery::class)->get($user);

    expect($suggestions)->toHaveCount(3)
        ->and($suggestions->pluck('id')->all())->each->toBeIn($network->modelKeys());
});

it('picks popular people from the most followed', function (): void {
    $user = User::factory()->create();

    $mostFollowed = User::factory()->count(20)->has(Commit::factory())->create()
        ->each(fn (User $popular) => $popular->followers()->attach(User::factory()->create()));
    $unknown = User::factory()->has(Commit::factory())->create();

    $suggestions = resolve(SuggestedUsersQuery::class)->get($user);

    expect($suggestions)->toHaveCount(3)
        ->and($suggestions->pluck('id')->all())->not->toContain($unknown->id)
        ->each->toBeIn($mostFollowed->modelKeys());
});

it('picks the 20 most followed among people who have committed', function (): void {
    $user = User::factory()->create();

    $mostFollowed = User::factory()->count(20)->has(Commit::factory())->create()
        ->each(fn (User $popular) => $popular->followers()->attach(User::factory()->create()));
    User::factory()->has(Commit::factory()->pending())->create()->followers()->attach(User::factory()->count(2)->create());

    $query = resolve(SuggestedUsersQuery::class);
    $suggested = collect(range(0, 6))->flatMap(fn (int $round): array => $query->get($user, round: $round)->pluck('id')->all());

    expect($suggested->unique()->sort()->values()->all())->toBe(collect($mostFollowed->modelKeys())->sort()->values()->all());
});

it('keeps the same suggestions in the same order during the day', function (): void {
    $user = User::factory()->create();
    vouchedFor($user, 3);
    popularUsers(5);

    $query = resolve(SuggestedUsersQuery::class);

    expect($query->get($user)->pluck('id')->all())->toBe($query->get($user)->pluck('id')->all());
});

it('limits the number of suggestions', function (): void {
    $user = User::factory()->create();

    User::factory()->count(5)->has(Commit::factory())->create();

    expect(resolve(SuggestedUsersQuery::class)->get($user))->toHaveCount(3)
        ->and(resolve(SuggestedUsersQuery::class)->get($user, limit: 2))->toHaveCount(2);
});

it('does not suggest muted or dismissed people or people across a block', function (): void {
    $user = User::factory()->create();
    $muted = User::factory()->has(Commit::factory())->create();
    $dismissed = User::factory()->has(Commit::factory())->create();
    $blocked = User::factory()->has(Commit::factory())->create();
    $blocker = User::factory()->has(Commit::factory())->create();
    $stranger = User::factory()->has(Commit::factory())->create();

    $user->muting()->attach($muted);
    $user->dismissedSuggestions()->attach($dismissed);
    $user->blocking()->attach($blocked);
    $blocker->blocking()->attach($user);

    $friend = User::factory()->create();
    $user->following()->attach($friend);
    $friend->following()->attach([$muted->id, $dismissed->id, $blocked->id, $blocker->id]);

    expect(resolve(SuggestedUsersQuery::class)->get($user)->pluck('id')->all())->toBe([$stranger->id]);
});

it('suggests other people on the next round', function (): void {
    $user = User::factory()->create();
    vouchedFor($user, 3);
    popularUsers(3);

    $query = resolve(SuggestedUsersQuery::class);

    $first = $query->get($user)->pluck('id')->all();
    $second = $query->get($user, round: 1)->pluck('id')->all();

    expect($first)->toHaveCount(3)
        ->and($second)->toHaveCount(3)
        ->and(array_intersect($first, $second))->toBeEmpty();
});

it('adds the next rounds as spares after the shown people', function (): void {
    $user = User::factory()->create();
    popularUsers(5);

    $query = resolve(SuggestedUsersQuery::class);

    $shown = $query->get($user)->pluck('id')->all();
    $withSpares = $query->get($user, spares: 3)->pluck('id')->all();

    expect($withSpares)->toHaveCount(5)
        ->and(array_slice($withSpares, 0, 3))->toBe($shown)
        ->and(array_slice($withSpares, 3))->toBe(array_values(array_diff($query->get($user, round: 1)->pluck('id')->all(), $shown)));
});

it('keeps the first round as the suggestions of the day', function (): void {
    $user = User::factory()->create();
    vouchedFor($user, 4);
    popularUsers(5);

    $query = resolve(SuggestedUsersQuery::class);

    expect($query->get($user, round: 0)->pluck('id')->all())->toBe($query->get($user)->pluck('id')->all());
});

it('goes back to the first suggestions after the last round', function (): void {
    $user = User::factory()->create();
    popularUsers(6);

    $query = resolve(SuggestedUsersQuery::class);

    expect($query->get($user, round: 2)->pluck('id')->all())->toBe($query->get($user)->pluck('id')->all())
        ->and($query->get($user, round: 3)->pluck('id')->all())->toBe($query->get($user, round: 1)->pluck('id')->all());
});

it('tops up the last round so it never shows fewer people', function (): void {
    $user = User::factory()->create();
    $popular = popularUsers(4);

    $query = resolve(SuggestedUsersQuery::class);

    $first = $query->get($user)->pluck('id')->all();
    $last = $query->get($user, round: 1)->pluck('id')->all();

    expect($last)->toHaveCount(3)
        ->and($last)->each->toBeIn($popular->modelKeys())
        ->and(array_diff($popular->modelKeys(), $first))->each->toBeIn($last);
});

it('keeps the same suggestions when there is nobody else to suggest', function (): void {
    $user = User::factory()->create();
    popularUsers(2);

    $query = resolve(SuggestedUsersQuery::class);

    expect($query->get($user, round: 1)->pluck('id')->all())->toBe($query->get($user)->pluck('id')->all());
});

it('marks the popular people who follow the user', function (): void {
    $user = User::factory()->create();
    [$follower, $stranger] = User::factory()->count(2)->has(Commit::factory())->create();

    $follower->following()->attach($user);

    $suggestions = resolve(SuggestedUsersQuery::class)->get($user)->keyBy('id');

    expect($suggestions)->toHaveCount(2)
        ->and($suggestions[$follower->id]->follows_you)->toBeTrue()
        ->and($suggestions[$stranger->id]->follows_you)->toBeFalse();
});

it('marks the people the user might know who follow the user', function (): void {
    $user = User::factory()->create();
    [$follower, $stranger] = vouchedFor($user, 2);

    $follower->following()->attach($user);

    $suggestions = resolve(SuggestedUsersQuery::class)->get($user)->keyBy('id');

    expect($suggestions)->toHaveCount(2)
        ->and($suggestions[$follower->id]->follows_you)->toBeTrue()
        ->and($suggestions[$stranger->id]->follows_you)->toBeFalse();
});

it('suggests people followed by the profile being viewed', function (): void {
    $viewer = User::factory()->create();
    $profile = User::factory()->create();
    $related = User::factory()->has(Commit::factory())->create();

    $profile->following()->attach($related);

    expect(resolve(SuggestedUsersQuery::class)->get($viewer, context: $profile)->pluck('id')->all())
        ->toContain($related->id);
});

it('suggests developers who participated in a visible thread', function (): void {
    $viewer = User::factory()->create();
    $author = User::factory()->create();
    $root = Commit::factory()->for($author)->create();
    $participant = User::factory()->has(Commit::factory())->create();

    Commit::factory()->for($participant)->for($root, 'parent')->create(['root_id' => $root->id]);

    expect(resolve(SuggestedUsersQuery::class)->get($viewer, context: $root)->pluck('id')->all())
        ->toContain($participant->id);
});

it('suggests authors of visible quotes in a thread', function (): void {
    $viewer = User::factory()->create();
    $root = Commit::factory()->create();
    $quoter = User::factory()->has(Commit::factory())->create();

    Commit::factory()->for($quoter)->create(['quoted_commit_id' => $root->id]);

    expect(resolve(SuggestedUsersQuery::class)->get($viewer, context: $root)->pluck('id')->all())
        ->toContain($quoter->id);
});

it('does not suggest participants whose only path to the thread is hidden', function (): void {
    $viewer = User::factory()->create();
    $root = Commit::factory()->create();
    $hiddenParent = User::factory()->create();
    $hiddenParticipant = User::factory()->has(Commit::factory())->create();
    $visibleParticipants = User::factory()->count(15)->has(Commit::factory())->create();

    $hiddenReply = Commit::factory()->for($hiddenParent)->for($root, 'parent')->create(['root_id' => $root->id]);
    Commit::factory()->for($hiddenParticipant)->for($hiddenReply, 'parent')->create(['root_id' => $root->id]);
    foreach ($visibleParticipants as $participant) {
        Commit::factory()->for($participant)->for($root, 'parent')->create(['root_id' => $root->id]);
    }

    $viewer->muting()->attach($hiddenParent);

    $suggestions = resolve(SuggestedUsersQuery::class)->get($viewer, context: $root)->pluck('id')->all();

    expect($suggestions)->toHaveCount(3)
        ->and($suggestions)->each->toBeIn($visibleParticipants->modelKeys())
        ->and($suggestions)->not->toContain($hiddenParticipant->id);
});

it('keeps visible replies under deleted commits in the contextual thread', function (): void {
    $viewer = User::factory()->create();
    $root = Commit::factory()->create();
    $deletedAuthor = User::factory()->has(Commit::factory())->create();
    $deletedReply = Commit::factory()->for($deletedAuthor)->for($root, 'parent')->create(['root_id' => $root->id]);
    $participant = User::factory()->has(Commit::factory())->create();
    Commit::factory()->for($participant)->for($deletedReply, 'parent')->create(['root_id' => $root->id]);
    $visibleParticipants = User::factory()->count(15)->has(Commit::factory())->create();

    foreach ($visibleParticipants as $visibleParticipant) {
        Commit::factory()->for($visibleParticipant)->for($root, 'parent')->create(['root_id' => $root->id]);
    }

    $deletedReply->delete();

    $suggestions = resolve(SuggestedUsersQuery::class)->get($viewer, limit: 16, context: $root)->pluck('id')->all();

    expect($suggestions)->toHaveCount(16)
        ->and($suggestions)->toContain($participant->id)
        ->and($suggestions)->not->toContain($deletedAuthor->id);
});

it('excludes muted and dismissed contextual candidates', function (): void {
    $viewer = User::factory()->create();
    $profile = User::factory()->create();
    $muted = User::factory()->has(Commit::factory())->create();
    $dismissed = User::factory()->has(Commit::factory())->create();
    $available = User::factory()->has(Commit::factory())->create();

    $profile->following()->attach([$muted->id, $dismissed->id]);
    $viewer->muting()->attach($muted);
    $viewer->dismissedSuggestions()->attach($dismissed);

    $suggestions = resolve(SuggestedUsersQuery::class)->get($viewer, context: $profile);

    expect($suggestions->pluck('id')->all())->toBe([$available->id]);
});

it('keeps contextual suggestions stable for the day and supplies different rounds and spares', function (): void {
    $viewer = User::factory()->create();
    $profile = User::factory()->create();
    $related = User::factory()->count(6)->has(Commit::factory())->create();
    vouchedFor($viewer, 6);

    $profile->following()->attach($related);

    $query = resolve(SuggestedUsersQuery::class);
    $first = $query->get($viewer, context: $profile)->pluck('id')->all();
    $second = $query->get($viewer, round: 1, context: $profile)->pluck('id')->all();
    $withSpares = $query->get($viewer, spares: 3, context: $profile)->pluck('id')->all();

    expect($first)->toHaveCount(3)
        ->and($first)->toBe($query->get($viewer, context: $profile)->pluck('id')->all())
        ->and(array_intersect($first, $second))->toBeEmpty()
        ->and(array_slice($withSpares, 0, 3))->toBe($first)
        ->and($withSpares)->toHaveCount(6);
});

it('prioritizes contextual suggestions across rounds and spares', function (): void {
    $viewer = User::factory()->create();
    $profile = User::factory()->create();
    $related = User::factory()->count(6)->has(Commit::factory())->create();
    $regular = vouchedFor($viewer, 6);

    $profile->following()->attach($related);

    $query = resolve(SuggestedUsersQuery::class);
    $first = $query->get($viewer, context: $profile)->pluck('id')->all();
    $second = $query->get($viewer, round: 1, context: $profile)->pluck('id')->all();
    $fallback = $query->get($viewer, round: 2, context: $profile)->pluck('id')->all();
    $withSpares = $query->get($viewer, spares: 3, context: $profile)->pluck('id')->all();

    expect($first)->toHaveCount(3)
        ->and(array_diff($first, $related->modelKeys()))->toBeEmpty()
        ->and($second)->toHaveCount(3)
        ->and(array_diff($second, $related->modelKeys()))->toBeEmpty()
        ->and(array_intersect($first, $second))->toBeEmpty()
        ->and(array_intersect($fallback, $regular->modelKeys()))->not->toBeEmpty()
        ->and(array_slice($withSpares, 0, 3))->toBe($first)
        ->and($withSpares)->toHaveCount(6)
        ->and(array_diff($withSpares, $related->modelKeys()))->toBeEmpty();
});

it('cycles after five full rounds of contextual suggestions without loading fallbacks', function (): void {
    $viewer = User::factory()->create();
    $profile = User::factory()->create();
    $related = User::factory()->count(18)->has(Commit::factory())->create();
    vouchedFor($viewer, 6);

    $profile->following()->attach($related);

    $query = resolve(SuggestedUsersQuery::class);
    $first = $query->get($viewer, context: $profile)->pluck('id')->all();
    $fifthRound = $query->get($viewer, round: 4, context: $profile)->pluck('id')->all();
    $nextRound = $query->get($viewer, round: 5, context: $profile)->pluck('id')->all();
    $withSpares = $query->get($viewer, spares: 12, context: $profile)->pluck('id')->all();

    expect($first)->toHaveCount(3)
        ->and(array_diff($first, $related->modelKeys()))->toBeEmpty()
        ->and($fifthRound)->toHaveCount(3)
        ->and(array_diff($fifthRound, $related->modelKeys()))->toBeEmpty()
        ->and($nextRound)->toBe($first)
        ->and($withSpares)->toHaveCount(15)
        ->and(array_diff($withSpares, $related->modelKeys()))->toBeEmpty();
});

it('uses regular suggestions to fill slots without contextual candidates', function (): void {
    $viewer = User::factory()->create();
    $profile = User::factory()->create();
    $contextual = User::factory()->has(Commit::factory())->create();
    $regular = vouchedFor($viewer, 2);

    $profile->following()->attach($contextual);

    $suggestions = resolve(SuggestedUsersQuery::class)->get($viewer, context: $profile)->pluck('id')->all();

    expect($suggestions)->toHaveCount(3)
        ->and($suggestions[0])->toBe($contextual->id)
        ->and(array_diff(array_slice($suggestions, 1), $regular->modelKeys()))->toBeEmpty();
});

it('does not use a blocked profile to find contextual candidates', function (): void {
    $viewer = User::factory()->create();
    $profile = User::factory()->create();
    $candidate = User::factory()->has(Commit::factory())->create();
    $other = User::factory()->has(Commit::factory())->create();

    $profile->following()->attach($candidate);
    $viewer->blocking()->attach($profile);

    $query = resolve(SuggestedUsersQuery::class);

    expect($query->get($viewer, context: $profile)->pluck('id')->all())
        ->toBe($query->get($viewer)->pluck('id')->all());
});

it('does not prioritize hidden, unapproved, deleted, muted, or blocked thread participants', function (): void {
    $viewer = User::factory()->create();
    $author = User::factory()->create();
    $root = Commit::factory()->for($author)->create();
    $visible = User::factory()->count(3)->has(Commit::factory())->create();
    $pending = User::factory()->has(Commit::factory())->create();
    $rejected = User::factory()->has(Commit::factory())->create();
    $deleted = User::factory()->has(Commit::factory())->create();
    $muted = User::factory()->has(Commit::factory())->create();
    $blocked = User::factory()->has(Commit::factory())->create();

    $visible->each(fn (User $user) => Commit::factory()->for($user)->for($root, 'parent')->create(['root_id' => $root->id]));
    Commit::factory()->for($pending)->for($root, 'parent')->pending()->create(['root_id' => $root->id]);
    Commit::factory()->for($rejected)->for($root, 'parent')->rejected()->create(['root_id' => $root->id]);
    Commit::factory()->for($deleted)->for($root, 'parent')->create(['root_id' => $root->id])->delete();
    Commit::factory()->for($muted)->for($root, 'parent')->create(['root_id' => $root->id]);
    Commit::factory()->for($blocked)->for($root, 'parent')->create(['root_id' => $root->id]);
    $viewer->muting()->attach($muted);
    $viewer->blocking()->attach($blocked);

    $suggestions = resolve(SuggestedUsersQuery::class)->get($viewer, context: $root)->pluck('id')->all();

    expect($suggestions)->toHaveCount(3)
        ->and(array_diff($suggestions, $visible->modelKeys()))->toBeEmpty();
});
