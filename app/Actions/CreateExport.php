<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Poll;
use App\Models\PollOption;
use App\Models\User;
use App\Queries\CommitBuilder;
use Generator;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

final readonly class CreateExport
{
    /**
     * @return array{schema_version: int, exported_at: string, profile: array<string, bool|int|string|null>, commits: Generator<int, array<string, int|string|null>>, polls: Generator<int, array{commit_id: string, duration_minutes: int, opened_at: string|null, options: list<string>, created_at: string}>, poll_votes: Generator<int, array{commit_id: string, option: string, created_at: string}>, following: Generator<int, array{username: string}>, muting: Generator<int, array{username: string}>, blocking: Generator<int, array{username: string}>}
     */
    public function handle(User $user): array
    {
        return [
            'schema_version' => 1,
            'exported_at' => now()->toIso8601String(),
            'profile' => [
                'username' => $user->username,
                'name' => $user->name,
                'email' => $user->email,
                'avatar_url' => $user->avatar_url,
                'bio' => $user->bio,
                'location' => $user->location,
                'website' => $user->website,
                'company' => $user->company,
                'has_sponsors_listing' => $user->has_sponsors_listing,
                'preferred_lane' => $user->preferred_lane,
                'created_at' => $user->created_at->toIso8601String(),
                'updated_at' => $user->updated_at->toIso8601String(),
            ],
            'commits' => $this->commits($user),
            'polls' => $this->polls($user),
            'poll_votes' => $this->pollVotes($user),
            'following' => $this->usernames($user->following()),
            'muting' => $this->usernames($user->muting()),
            'blocking' => $this->usernames($user->blocking()),
        ];
    }

    /**
     * @return Generator<int, array<string, int|string|null>>
     */
    private function commits(User $user): Generator
    {
        foreach ($user->commits()->select(['id', 'parent_id', 'quoted_commit_id', 'series_id', 'series_position', 'series_size', 'body', 'status', 'created_at', 'updated_at'])->lazyById(500) as $commit) {
            yield [
                'id' => $commit->id,
                'parent_id' => $commit->parent_id,
                'quoted_commit_id' => $commit->quoted_commit_id,
                'series_id' => $commit->series_id,
                'series_position' => $commit->series_position,
                'series_size' => $commit->series_size,
                'body' => $commit->body,
                'status' => $commit->status->value,
                'created_at' => $commit->created_at->toIso8601String(),
                'updated_at' => $commit->updated_at->toIso8601String(),
            ];
        }
    }

    /**
     * @return Generator<int, array{commit_id: string, duration_minutes: int, opened_at: string|null, options: list<string>, created_at: string}>
     */
    private function polls(User $user): Generator
    {
        $polls = Poll::query()
            ->whereHas('commit', fn (CommitBuilder $query): CommitBuilder => $query->where('user_id', $user->id))
            ->with('options')
            ->lazyById(500);

        foreach ($polls as $poll) {
            yield [
                'commit_id' => $poll->commit_id,
                'duration_minutes' => $poll->duration->value,
                'opened_at' => $poll->opened_at?->toIso8601String(),
                'options' => array_values($poll->options->map(fn (PollOption $option): string => $option->label)->all()),
                'created_at' => $poll->created_at->toIso8601String(),
            ];
        }
    }

    /**
     * Only the user's own choices: never who else voted.
     *
     * @return Generator<int, array{commit_id: string, option: string, created_at: string}>
     */
    private function pollVotes(User $user): Generator
    {
        foreach ($user->pollVotes()->with(['poll', 'option'])->lazyById(500) as $vote) {
            yield [
                'commit_id' => $vote->poll->commit_id,
                'option' => $vote->option->label,
                'created_at' => $vote->created_at->toIso8601String(),
            ];
        }
    }

    /**
     * @param  BelongsToMany<User, User>  $users
     * @return Generator<int, array{username: string}>
     */
    private function usernames(BelongsToMany $users): Generator
    {
        foreach ($users->select(['users.id', 'users.username'])->lazyById(500, 'users.id', 'id') as $user) {
            yield ['username' => $user->username];
        }
    }
}
