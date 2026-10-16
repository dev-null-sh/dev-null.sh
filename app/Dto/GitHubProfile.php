<?php

declare(strict_types=1);

namespace App\Dto;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Laravel\Socialite\Two\User as GitHubUser;

/**
 * The public profile of a GitHub account, as devnull mirrors it.
 */
final readonly class GitHubProfile
{
    public function __construct(
        public string $username,
        public string $name,
        public ?string $avatarUrl,
        public ?string $bio,
        public ?string $location,
        public ?string $website,
        public ?string $company,
    ) {
        //
    }

    public static function fromSocialite(GitHubUser $githubUser): self
    {
        /** @var array<string, mixed> $raw */
        $raw = $githubUser->getRaw();

        return self::from(
            username: (string) $githubUser->getNickname(),
            name: $githubUser->getName(),
            avatarUrl: $githubUser->getAvatar(),
            bio: Arr::get($raw, 'bio'),
            location: Arr::get($raw, 'location'),
            blog: Arr::get($raw, 'blog'),
            company: Arr::get($raw, 'company'),
        );
    }

    /**
     * @param  array<string, mixed>  $account  A user from the GitHub REST API.
     */
    public static function fromAccount(array $account): self
    {
        return self::from(
            username: Arr::string($account, 'login'),
            name: Arr::get($account, 'name'),
            avatarUrl: Arr::get($account, 'avatar_url'),
            bio: Arr::get($account, 'bio'),
            location: Arr::get($account, 'location'),
            blog: Arr::get($account, 'blog'),
            company: Arr::get($account, 'company'),
        );
    }

    /**
     * @return array{username: string, name: string, avatar_url: string|null, bio: string|null, location: string|null, website: string|null, company: string|null}
     */
    public function attributes(): array
    {
        return [
            'username' => $this->username,
            'name' => $this->name,
            'avatar_url' => $this->avatarUrl,
            'bio' => $this->bio,
            'location' => $this->location,
            'website' => $this->website,
            'company' => $this->company,
        ];
    }

    private static function from(string $username, mixed $name, mixed $avatarUrl, mixed $bio, mixed $location, mixed $blog, mixed $company): self
    {
        return new self(
            username: $username,
            name: self::nullIfBlank($name) ?? $username,
            avatarUrl: self::nullIfBlank($avatarUrl),
            bio: self::nullIfBlank($bio),
            location: self::nullIfBlank($location),
            website: self::website($blog),
            company: self::nullIfBlank($company),
        );
    }

    private static function website(mixed $value): ?string
    {
        $website = self::nullIfBlank($value);

        if ($website === null || Str::startsWith($website, ['http://', 'https://'])) {
            return $website;
        }

        return 'https://'.$website;
    }

    private static function nullIfBlank(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = mb_trim($value);

        return $value === '' ? null : $value;
    }
}
