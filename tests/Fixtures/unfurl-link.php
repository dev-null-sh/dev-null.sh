<?php

declare(strict_types=1);

use App\Actions\UpdateLinkPreview;
use App\Jobs\UnfurlLink;
use App\Models\LinkPreview;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;

const RELEASE_TIMEOUT_SECONDS = 20;
const POLL_INTERVAL_MICROSECONDS = 10_000;

require __DIR__.'/../../vendor/autoload.php';

if (($source = getenv('PEST_MUTATION_FILE')) !== false && getenv('PEST_MUTATION_TESTING') === realpath(__DIR__.'/../../app/Jobs/UnfurlLink.php')) {
    require $source;
}

$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$preview = LinkPreview::query()->findOrFail($argv[1]);
$directory = $argv[2];
$isFirst = $argv[3] === 'first';
$job = new UnfurlLink($preview);
$pid = DB::selectOne('select pg_backend_pid() as pid')->pid;
fwrite(STDOUT, "PID:{$pid}\n");

Http::preventStrayRequests();
Http::fake(function () use ($directory, $isFirst) {
    file_put_contents($directory.'/requests', "request\n", FILE_APPEND | LOCK_EX);

    if ($isFirst) {
        fwrite(STDOUT, "FETCHING\n");
        $deadline = microtime(true) + RELEASE_TIMEOUT_SECONDS;

        while (! is_file($directory.'/release')) {
            throw_if(microtime(true) > $deadline, RuntimeException::class, 'Concurrent unfurl fixture was not released.');

            Sleep::usleep(POLL_INTERVAL_MICROSECONDS);
        }
    }

    return Http::response('<title>Concurrent preview</title>', 200, ['Content-Type' => 'text/html']);
});

$job->handle($app->make(UpdateLinkPreview::class));
