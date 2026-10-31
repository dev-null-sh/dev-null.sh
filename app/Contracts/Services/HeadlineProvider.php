<?php

declare(strict_types=1);

namespace App\Contracts\Services;

interface HeadlineProvider
{
    /**
     * Bodies and results share batch-local indexes, never account or commit identifiers.
     *
     * @param  array<int, string>  $bodies
     * @return list<int>
     */
    public function candidates(array $bodies): array;

    /**
     * @param  array<int, string>  $bodies
     * @param  list<string>  $existing
     * @return array<int, list<string>>
     */
    public function matches(array $bodies, array $existing): array;

    /**
     * @param  array<int, string>  $bodies
     * @return array<int, string>
     */
    public function generate(array $bodies): array;

    /**
     * Return one name per input, reusing the first retained name for similar headlines.
     *
     * @param  array<int, string>  $headlines
     * @return array<int, string>
     */
    public function deduplicate(array $headlines): array;
}
