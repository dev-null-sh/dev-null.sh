<?php

declare(strict_types=1);

use App\Models\LinkPreview;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Sleep;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

const UNFURL_CONCURRENCY_TIMEOUT_SECONDS = 10;
const UNFURL_LOCK_POLL_MICROSECONDS = 10_000;

it('concurrent duplicate workers fetch a shared preview only once', function (): void {
    $connection = DB::getDefaultConnection();
    $settings = config()->array('database.connections.'.$connection);
    config()->set('database.connections.unfurl_concurrency', $settings);
    $fixtures = DB::connection('unfurl_concurrency');
    $id = (string) Str::uuid();
    $url = 'https://93.184.216.34/concurrent/'.$id;
    $directory = sys_get_temp_dir().'/unfurl-concurrency-'.$id;
    File::makeDirectory($directory);

    // A separate connection commits this fixture so both worker processes can see it.
    $fixtures->table('link_previews')->insert([
        'id' => $id,
        'url' => $url,
        'url_hash' => hash('sha256', $url),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $environment = [
        'APP_ENV' => 'testing',
        'DB_CONNECTION' => $connection,
        'DB_URL' => '',
        'DB_HOST' => config()->string('database.connections.'.$connection.'.host'),
        'DB_PORT' => (string) config('database.connections.'.$connection.'.port'),
        'DB_DATABASE' => config()->string('database.connections.'.$connection.'.database'),
        'DB_USERNAME' => config()->string('database.connections.'.$connection.'.username'),
        'DB_PASSWORD' => config()->string('database.connections.'.$connection.'.password'),
        'CACHE_STORE' => 'array',
    ];
    $fixture = base_path('tests/Fixtures/unfurl-link.php');
    $first = new Process([PHP_BINARY, $fixture, $id, $directory, 'first'], base_path(), $environment);
    $second = new Process([PHP_BINARY, $fixture, $id, $directory, 'second'], base_path(), $environment);

    try {
        Sleep::fake(false);
        $first->setTimeout(UNFURL_CONCURRENCY_TIMEOUT_SECONDS)->start();
        $first->waitUntil(static fn (string $type, string $output): bool => $type === Process::OUT && str_contains($output, 'FETCHING'));
        expect($fixtures->selectOne('select id from link_previews where id = ? for key share nowait', [$id])?->id)->toBe($id);
        $second->setTimeout(UNFURL_CONCURRENCY_TIMEOUT_SECONDS)->start();
        $second->waitUntil(static fn (string $type, string $output): bool => $type === Process::OUT && str_contains($output, 'PID:'));
        throw_unless(preg_match('/PID:(\d+)/', $second->getOutput(), $match) === 1, RuntimeException::class, 'Second worker did not report its database session.');

        $deadline = microtime(true) + UNFURL_CONCURRENCY_TIMEOUT_SECONDS;
        $isWaiting = false;

        while ($second->isRunning() && microtime(true) < $deadline) {
            $isWaiting = $fixtures->selectOne('select wait_event_type from pg_stat_activity where pid = ?', [(int) $match[1]])?->wait_event_type === 'Lock';

            if ($isWaiting) {
                break;
            }

            Sleep::usleep(UNFURL_LOCK_POLL_MICROSECONDS);
        }

        touch($directory.'/release');
        $first->wait();
        $second->wait();

        expect($first->isSuccessful())->toBeTrue($first->getErrorOutput())
            ->and($second->isSuccessful())->toBeTrue($second->getErrorOutput())
            ->and(file_get_contents($directory.'/requests'))->toBe("request\n")
            ->and($isWaiting)->toBeTrue()
            ->and(LinkPreview::query()->findOrFail($id)->title)->toBe('Concurrent preview');
    } finally {
        Sleep::fake();
        touch($directory.'/release');
        $first->stop();
        $second->stop();
        $fixtures->table('link_previews')->where('id', $id)->delete();
        DB::purge('unfurl_concurrency');
        File::deleteDirectory($directory);
    }
});
