<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\NotificationType;
use App\Models\Bookmark;
use App\Models\CommitImage;
use App\Models\User;
use Generator;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use stdClass;

final readonly class CreateExport
{
    /**
     * Everything stored about the user, deleted commits included, but never secrets like the remember token or session ids.
     *
     * @return array{schema_version: int, exported_at: string, profile: array<string, bool|int|string|null>, commits: Generator<int, array<string, mixed>>, stars: Generator<int, string>, bookmarks: Generator<int, string>, following: Generator<int, array{username: string}>, followers: Generator<int, array{username: string}>, muting: Generator<int, array{username: string}>, blocking: Generator<int, array{username: string}>, notifications: Generator<int, array<string, string|null>>, ssh_keys: array<mixed>, sessions: Collection<int, stdClass>}
     */
    public function handle(User $user): array
    {
        return [
            'schema_version' => 2,
            'exported_at' => now()->toIso8601String(),
            'profile' => [
                'github_id' => $user->github_id,
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
                'pinned_commit_id' => $user->pinned_commit_id,
                'created_at' => $user->created_at->toIso8601String(),
                'updated_at' => $user->updated_at->toIso8601String(),
            ],
            'commits' => $this->commits($user),
            'stars' => $this->stars($user),
            'bookmarks' => $this->bookmarks($user),
            'following' => $this->usernames($user->following()),
            'followers' => $this->usernames($user->followers()),
            'muting' => $this->usernames($user->muting()),
            'blocking' => $this->usernames($user->blocking()),
            'notifications' => $this->notifications($user),
            'ssh_keys' => $user->sshKeys()->pluck('fingerprint')->all(),
            'sessions' => $this->sessions($user),
        ];
    }

    /**
     * @return Generator<int, array<string, mixed>>
     */
    private function commits(User $user): Generator
    {
        foreach ($user->commits()->withTrashed()->with('images')->lazyById(500) as $commit) {
            yield [
                'id' => $commit->id,
                'parent_id' => $commit->parent_id,
                'quoted_commit_id' => $commit->quoted_commit_id,
                'series_id' => $commit->series_id,
                'series_position' => $commit->series_position,
                'series_size' => $commit->series_size,
                'body' => $commit->body,
                'status' => $commit->status->value,
                'images' => $commit->images->map(fn (CommitImage $image): string => $image->url())->all(),
                'created_at' => $commit->created_at->toIso8601String(),
                'updated_at' => $commit->updated_at->toIso8601String(),
                'deleted_at' => $commit->deleted_at?->toIso8601String(),
            ];
        }
    }

    /**
     * @return Generator<int, string>
     */
    private function stars(User $user): Generator
    {
        foreach ($user->starredCommits()->withTrashed()->select('commits.id')->lazyById(500, 'commits.id', 'id') as $commit) {
            yield $commit->id;
        }
    }

    /**
     * @return Generator<int, string>
     */
    private function bookmarks(User $user): Generator
    {
        foreach (Bookmark::query()->where('user_id', $user->id)->lazyById(500) as $bookmark) {
            yield $bookmark->commit_id;
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

    /**
     * Moderators stay anonymous, as they are on the notifications page.
     *
     * @return Generator<int, array<string, string|null>>
     */
    private function notifications(User $user): Generator
    {
        foreach ($user->notifications()->with('actor')->lazyById(500) as $notification) {
            $isModeration = in_array($notification->type, [NotificationType::Approved, NotificationType::Rejected], true);

            yield [
                'type' => $notification->type->value,
                'actor' => $isModeration ? null : $notification->actor->username,
                'commit_id' => $notification->commit_id,
                'reason' => $notification->reason,
                'read_at' => $notification->read_at?->toIso8601String(),
                'created_at' => $notification->created_at->toIso8601String(),
            ];
        }
    }

    /**
     * Never the session id or payload: either could be used to take over the session.
     *
     * @return Collection<int, stdClass>
     */
    private function sessions(User $user): Collection
    {
        return DB::table('sessions')
            ->where('user_id', $user->id)
            ->get(['ip_address', 'user_agent', 'last_activity']);
    }
}
