<?php

declare(strict_types=1);

use App\Queries\ConnectivityQuery;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Database\ConnectionResolverInterface;

it('reaches the database and the cache', function (): void {
    expect(resolve(ConnectivityQuery::class)->get())
        ->toBe(['database' => true, 'cache' => true]);
});

it('reports the database and the cache as down when they cannot be reached', function (): void {
    $database = Mockery::mock(ConnectionResolverInterface::class);
    $database->shouldReceive('connection')->andThrow(new PDOException('SQLSTATE[HY000] [2002] Connection refused'));

    $cache = Mockery::mock(Repository::class);
    $cache->shouldReceive('get')->andThrow(new RuntimeException('Connection refused'));

    expect(new ConnectivityQuery($database, $cache)->get())
        ->toBe(['database' => false, 'cache' => false]);
});
