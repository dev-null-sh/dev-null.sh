<?php

declare(strict_types=1);

namespace App\Dto;

use Illuminate\Support\Arr;

/**
 * A GitHub account that isn't on /dev/null, as its unclaimed profile shows it.
 */
final readonly class GitHubAccount
{
    private function __construct(
        public string $id,
        public GitHubProfile $profile,
    ) {
        //
    }

    /**
     * Maps a user object of the GitHub REST API, such as `GET /users/{username}`.
     *
     * @param  array<array-key, mixed>  $account
     */
    public static function fromAccount(array $account): self
    {
        $id = Arr::get($account, 'id');
        assert(is_int($id));

        return new self((string) $id, GitHubProfile::fromAccount($account));
    }

    /**
     * @return array{username: string, name: string, avatar_url: string|null}
     */
    public function toArray(): array
    {
        return [
            'username' => $this->profile->username,
            'name' => $this->profile->name,
            'avatar_url' => $this->profile->avatarUrl,
        ];
    }
}
