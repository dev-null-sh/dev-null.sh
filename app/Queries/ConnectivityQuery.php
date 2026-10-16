<?php

declare(strict_types=1);

namespace App\Queries;

use Closure;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Database\ConnectionResolverInterface;
use Throwable;

final readonly class ConnectivityQuery
{
    public function __construct(private ConnectionResolverInterface $database, private Repository $cache)
    {
        //
    }

    /**
     * Each check is one round trip to the default connection or store: a `select 1`, and a read of a key that is
     * never written.
     *
     * @return array{database: bool, cache: bool}
     */
    public function get(): array
    {
        return [
            'database' => $this->reaches(fn (): array => $this->database->connection()->select('select 1')),
            'cache' => $this->reaches(fn (): mixed => $this->cache->get('operations:ping')),
        ];
    }

    private function reaches(Closure $probe): bool
    {
        try {
            $probe();
        } catch (Throwable) {
            return false;
        }

        return true;
    }
}
