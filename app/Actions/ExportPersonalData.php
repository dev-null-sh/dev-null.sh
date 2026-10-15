<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\User;
use Generator;

final readonly class ExportPersonalData
{
    /**
     * @return array{schema_version: int, exported_at: string, profile: array<string, int|string|null>, commits: Generator<int, array<string, string|null>>, following: Generator<int, array{username: string}>}
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
                'preferred_lane' => $user->preferred_lane,
                'created_at' => $user->created_at->toIso8601String(),
                'updated_at' => $user->updated_at->toIso8601String(),
            ],
            'commits' => $this->commits($user),
            'following' => $this->following($user),
        ];
    }

    /**
     * @return Generator<int, array<string, string|null>>
     */
    private function commits(User $user): Generator
    {
        foreach ($user->commits()->select(['id', 'parent_id', 'body', 'status', 'created_at', 'updated_at'])->lazyById(500) as $commit) {
            yield [
                'id' => $commit->id,
                'parent_id' => $commit->parent_id,
                'body' => $commit->body,
                'status' => $commit->status->value,
                'created_at' => $commit->created_at->toIso8601String(),
                'updated_at' => $commit->updated_at->toIso8601String(),
            ];
        }
    }

    /**
     * @return Generator<int, array{username: string}>
     */
    private function following(User $user): Generator
    {
        foreach ($user->following()->select(['users.id', 'users.username'])->lazyById(500, 'users.id', 'id') as $followed) {
            yield ['username' => $followed->username];
        }
    }
}
