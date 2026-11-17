<?php

declare(strict_types=1);

namespace App\Contracts\Services;

/**
 * Filters the nearest results of a search by meaning down to those really related to the query.
 */
interface RelevanceProvider
{
    /**
     * Whether each body is meaningfully related to the query, in the order they were given. Anything but a clear
     * answer counts as relevant, so a flaky answer never hides a post.
     *
     * @param  list<string>  $bodies
     * @return list<bool>
     */
    public function decideRelevance(string $query, array $bodies, bool $related = false): array;
}
