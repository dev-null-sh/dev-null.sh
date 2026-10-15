<?php

declare(strict_types=1);

use App\Models\User;
use App\Queries\NotificationGroupsQuery;
use Illuminate\Support\Facades\DB;

it('compiles the grouping window for each database dialect with bound recipient and filter values', function (string $driver, string $gap): void {
    $user = User::factory()->make(['id' => '019c1234-5678-7000-8000-123456789abc']);
    $original = DB::getDefaultConnection();
    $connection = 'notification_query_'.$driver;
    config(['database.connections.'.$connection => [
        'driver' => $driver,
        'database' => ':memory:',
        'prefix' => '',
    ]]);
    DB::setDefaultConnection($connection);

    try {
        $query = resolve(NotificationGroupsQuery::class)->events($user, 'mentions');
        $sql = $query->toSql();
        $bindings = $query->getBindings();

        expect($sql)->toContain($gap)->not->toContain($user->id);
        expect($bindings[0])->toBe($user->id);
        expect(array_slice($bindings, -1))->toBe(['mentioned']);
        expect(mb_substr_count($sql, '?'))->toBe(count($bindings));
    } finally {
        DB::setDefaultConnection($original);
        DB::purge($connection);
    }
})->with([
    'SQLite' => ['sqlite', 'unixepoch(created_at) - unixepoch(previous_match_at)'],
    'MySQL' => ['mysql', 'TIMESTAMPDIFF(SECOND, previous_match_at, created_at)'],
    'MariaDB' => ['mariadb', 'TIMESTAMPDIFF(SECOND, previous_match_at, created_at)'],
    'PostgreSQL' => ['pgsql', 'EXTRACT(EPOCH FROM (created_at - previous_match_at))'],
]);
