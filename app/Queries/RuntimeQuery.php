<?php

declare(strict_types=1);

namespace App\Queries;

/**
 * @phpstan-type Runtime array{environment: string, php: string, laravel: string, database: string, cache: string, queue: string}
 */
final readonly class RuntimeQuery
{
    /**
     * What the app runs on, by name only: the drivers and stores, never their hosts or credentials.
     *
     * @return Runtime
     */
    public function get(): array
    {
        return [
            'environment' => app()->environment(),
            'php' => PHP_VERSION,
            'laravel' => app()->version(),
            'database' => config()->string('database.connections.'.config()->string('database.default').'.driver'),
            'cache' => config()->string('cache.default'),
            'queue' => config()->string('queue.default'),
        ];
    }
}
