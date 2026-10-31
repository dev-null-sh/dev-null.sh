<?php

declare(strict_types=1);

use App\Actions\CreateConversation;
use App\Models\User;
use App\Queries\ConversationThreadQuery;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

it('publishes concurrent Remotes messages in cursor order', function (): void {
    $database = DB::connection()->getDatabaseName();
    $originalConnection = DB::getDefaultConnection();

    // RefreshDatabase wraps the ordinary connection in a transaction. These fixtures must be
    // committed to be visible to the two independent worker processes.
    config()->set('database.connections.cursor_probe', [
        ...config('database.connections.pgsql'),
        'database' => $database,
    ]);

    DB::setDefaultConnection('cursor_probe');

    try {
        $alice = User::factory()->withRemotes()->create();
        $bob = User::factory()->withRemotes()->create();
        $conversation = resolve(CreateConversation::class)->handle($alice, $bob);
    } finally {
        DB::setDefaultConnection($originalConnection);
    }

    $directory = sys_get_temp_dir().'/devnull-cursor-'.bin2hex(random_bytes(8));
    mkdir($directory, 0700);
    $release = $directory.'/release';
    $ready = $directory.'/ready';
    $firstFinished = $directory.'/first';
    $secondFinished = $directory.'/second';
    $secondTag = 'devnull-cursor-'.bin2hex(random_bytes(8));
    $processes = [];

    $spawn = function (string $sender, string $mode, string $finished, string $tag) use ($database, $conversation, $ready, $release, &$processes): void {
        $environment = [
            ...getenv(),
            'APP_ENV' => 'testing',
            'APP_KEY' => (string) config('app.key'),
            'DB_CONNECTION' => 'pgsql',
            'DB_DATABASE' => $database,
            'DB_HOST' => (string) config('database.connections.pgsql.host'),
            'DB_PORT' => (string) config('database.connections.pgsql.port'),
            'DB_USERNAME' => (string) config('database.connections.pgsql.username'),
            'DB_PASSWORD' => (string) config('database.connections.pgsql.password'),
            'QUEUE_CONNECTION' => 'sync',
            'WEB_PUSH_PUBLIC_KEY' => '',
            'WEB_PUSH_PRIVATE_KEY' => '',
        ];

        $process = proc_open(
            [PHP_BINARY, base_path('tests/Support/RemotesCursorWorker.php'), $sender, $conversation->id, $mode, $ready, $release, $finished, $tag],
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            base_path(),
            $environment,
        );

        throw_unless(is_resource($process), RuntimeException::class, 'Could not start the Remotes concurrency worker.');

        fclose($pipes[0]);
        $processes[] = [$process, $pipes];
    };

    $await = static function (Closure $condition, string $description): void {
        $deadline = microtime(true) + 20;

        while (! $condition()) {
            throw_if(microtime(true) >= $deadline, RuntimeException::class, 'Timed out: '.$description);

            time_nanosleep(0, 20_000_000);
        }
    };

    try {
        $spawn($alice->id, 'hold', $firstFinished, 'devnull-cursor-first');
        $await(fn (): bool => is_file($ready), 'first writer did not reach its insert');

        $spawn($bob->id, 'send', $secondFinished, $secondTag);
        $outcome = null;

        $await(function () use (&$outcome, $secondFinished, $secondTag): bool {
            if (is_file($secondFinished)) {
                $outcome = 'committed_before_first';
            } else {
                $waiting = DB::connection('cursor_probe')->selectOne(
                    'select count(*) as total from pg_stat_activity where application_name = ? and wait_event_type = ?',
                    [$secondTag, 'Lock'],
                );

                if ((int) $waiting->total > 0) {
                    $outcome = 'waiting_for_first';
                }
            }

            return $outcome !== null;
        }, 'second writer neither committed nor waited on the first');

        // Without a lock before INSERT, the second worker commits its higher ID while the
        // first worker is still paused inside its transaction. A polling cursor then loses
        // the first message forever.
        expect($outcome)->toBe('waiting_for_first');

        file_put_contents($release, 'go');

        $await(fn (): bool => is_file($firstFinished) && is_file($secondFinished), 'workers did not commit');

        $firstId = (int) file_get_contents($firstFinished);
        $secondId = (int) file_get_contents($secondFinished);
        $messages = resolve(ConversationThreadQuery::class)->get($alice, $bob)['messages']->pluck('id')->all();
        $incremental = resolve(ConversationThreadQuery::class)->get($alice, $bob, $firstId)['messages']->pluck('id')->all();

        expect($firstId)->toBeLessThan($secondId)
            ->and($messages)->toBe([$firstId, $secondId])
            ->and($incremental)->toBe([$secondId]);
    } finally {
        file_put_contents($release, 'go');

        foreach ($processes as [$process, $pipes]) {
            $deadline = microtime(true) + 5;

            while (proc_get_status($process)['running'] && microtime(true) < $deadline) {
                time_nanosleep(0, 20_000_000);
            }

            if (proc_get_status($process)['running']) {
                proc_terminate($process);
            }

            fclose($pipes[1]);
            fclose($pipes[2]);
            proc_close($process);
        }

        DB::connection('cursor_probe')->table('conversations')->where('id', $conversation->id)->delete();
        DB::connection('cursor_probe')->table('users')->whereIn('id', [$alice->id, $bob->id])->delete();
        DB::connection('cursor_probe')->table('profiles')->whereIn('github_id', [$alice->github_id, $bob->github_id])->delete();
        DB::purge('cursor_probe');

        foreach (glob($directory.'/*') ?: [] as $file) {
            File::delete($file);
        }

        rmdir($directory);
    }
});
