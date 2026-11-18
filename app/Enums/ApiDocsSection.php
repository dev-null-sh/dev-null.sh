<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * A page of the API docs: a guide to how the API works, in reading order.
 */
enum ApiDocsSection: string
{
    case Introduction = 'introduction';

    public function title(): string
    {
        return $this->name;
    }

    public function description(): string
    {
        return match ($this) {
            self::Introduction => 'What the API is for, and where it lives',
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
