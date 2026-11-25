<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * A page of the API docs: a guide to how the API works, in reading order.
 */
enum ApiDocsSection: string
{
    case Introduction = 'introduction';
    case Authentication = 'authentication';
    case RateLimits = 'rate-limits';
    case Pagination = 'pagination';
    case Errors = 'errors';

    public function title(): string
    {
        return match ($this) {
            self::RateLimits => 'Rate limits',
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
        };
    }

    /**
     * @return array{slug: string, title: string, description: string}
     */
    public function toArray(): array
    {
        return [
            'slug' => $this->value,
            'title' => $this->title(),
            'description' => $this->description(),
        ];
    }
}
