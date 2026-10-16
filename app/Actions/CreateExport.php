<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\User;
use Generator;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

final readonly class CreateExport
{
    /**
     * @return array{schema_version: int, exported_at: string, profile: array<string, bool|int|string|null>, commits: Generator<int, array<string, int|string|null>>, following: Generator<int, array{username: string}>, muting: Generator<int, array{username: string}>, blocking: Generator<int, array{username: string}>}
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
        foreach ($user->commits()->select(['id', 'parent_id', 'series_id', 'series_position', 'series_size', 'body', 'status', 'created_at', 'updated_at'])->lazyById(500) as $commit) {
            yield [
                'id' => $commit->id,
                'parent_id' => $commit->parent_id,
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
