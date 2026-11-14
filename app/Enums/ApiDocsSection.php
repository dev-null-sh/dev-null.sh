<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * A page of the API docs: a guide to how the API works, or a group of its reference, named after the first segment of
 * the paths it lists, as the OpenAPI spec groups them.
 */
enum ApiDocsSection: string
{
    case Introduction = 'introduction';
    case Authentication = 'authentication';
    case RateLimits = 'rate-limits';
    case Pagination = 'pagination';
    case Errors = 'errors';
    case Users = 'users';
    case Commits = 'commits';
    case Organizations = 'organizations';
    case Me = 'me';
    case Search = 'search';
    case ScheduledCommits = 'scheduled-commits';
    case Remotes = 'remotes';
    case Notifications = 'notifications';
    case Headlines = 'headlines';
    case Issues = 'issues';

    /**
     * The guides in reading order, then the groups of the reference.
     *
     * @return list<self>
     */
    public static function ordered(): array
    {
        return [...array_values(array_filter(self::cases(), fn (self $section): bool => $section->isGuide())), ...self::reference()];
    }

    /**
     * The groups of the reference alphabetically, so a reader finds one by its name, wherever they are listed.
     *
     * @return list<self>
     */
    public static function reference(): array
    {
        $groups = array_values(array_filter(self::cases(), fn (self $section): bool => ! $section->isGuide()));
        usort($groups, fn (self $a, self $b): int => strcmp($a->title(), $b->title()));

        return $groups;
    }

    public function isGuide(): bool
    {
        return in_array($this, [self::Introduction, self::Authentication, self::RateLimits, self::Pagination, self::Errors], true);
    }

    public function title(): string
    {
        return match ($this) {
            self::RateLimits => 'Rate limits',
            self::ScheduledCommits => 'Scheduled commits',
            default => $this->name,
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Introduction => 'What the API is for, and where it lives',
            self::Authentication => 'Reading as anyone, or signing requests as someone',
            self::RateLimits => 'How many requests a minute, and what happens past that',
            self::Pagination => 'Following a list from one page to the next',
            self::Errors => 'What went wrong, and the code that says which error it is',
            self::Users => 'Profiles, what they committed, and who follows whom',
            self::Commits => 'Reading, writing, starring and replying to commits',
            self::Organizations => 'GitHub organizations, their commits and their mentions',
            self::Me => 'The signed-in user: their account, settings and devices',
            self::Search => 'People, organizations and commits, by name or meaning',
            self::ScheduledCommits => 'Commits waiting for their time to be posted',
            self::Remotes => 'Conversations, orgs, messages and calls',
            self::Notifications => 'What happened to the user, grouped as the inbox shows it',
            self::Headlines => 'Hot topics: the headlines the aside shows, each with its recap',
            self::Issues => 'Feature requests and bug reports, opened on GitHub',
        };
    }

    /**
     * @return array{slug: string, title: string, description: string, kind: 'guide'|'reference'}
     */
    public function toArray(): array
    {
        return [
            'slug' => $this->value,
            'title' => $this->title(),
            'description' => $this->description(),
            'kind' => $this->isGuide() ? 'guide' : 'reference',
        ];
    }
}
