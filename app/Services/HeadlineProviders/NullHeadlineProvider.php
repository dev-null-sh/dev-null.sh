<?php

declare(strict_types=1);

namespace App\Services\HeadlineProviders;

use App\Contracts\Services\HeadlineProvider;

final readonly class NullHeadlineProvider implements HeadlineProvider
{
    /**
     * @param  array<int, string>  $bodies
     * @return list<int>
     */
    public function candidates(array $bodies): array
    {
        return [];
    }

    /**
     * @param  array<int, string>  $bodies
     * @param  list<string>  $existing
     * @return array<int, list<string>>
     */
    public function matches(array $bodies, array $existing): array
    {
        return [];
    }

    /**
     * @param  array<int, string>  $bodies
     * @return array<int, string>
     */
    public function generate(array $bodies): array
    {
        return [];
    }

    /**
     * @param  array<int, string>  $headlines
     * @return array<int, string>
     */
    public function deduplicate(array $headlines): array
    {
        return [];
    }
}
