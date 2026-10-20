<?php

declare(strict_types=1);

use App\Actions\CreateCommit;
use App\Actions\UpdateCommit;
use App\Actions\UpdateCommitStatuses;
use App\Enums\CommitStatus;
use App\Exceptions\CommitSourceChanged;
use App\Http\Requests\UpdateCommitRequest;
use App\Jobs\CheckUserLevel;
use App\Models\Commit;
use App\Models\User;
use Illuminate\Bus\UniqueLock;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Sleep;
use Illuminate\Validation\ValidationException;

require getcwd().'/vendor/autoload.php';
$app = require getcwd().'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

throw_if(! app()->environment('testing') || DB::connection()->getDatabaseName() !== 'testing', RuntimeException::class, 'This check requires APP_ENV=testing and DB_DATABASE=testing.');

Queue::fake();
foreach ([false, true] as $quote) {
    foreach ([false, true] as $interactionWins) {
        $author = User::factory()->create();
        $actor = User::factory()->create();
        $source = Commit::factory()->for($author)->create(['body' => 'Original']);
        $marker = 'amend-race-'.getmypid();
        $sockets = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
        DB::purge();
        $pid = pcntl_fork();
        throw_if($pid === -1, RuntimeException::class, 'Could not fork');

        if ($pid === 0) {
            fclose($sockets[0]);
            fgets($sockets[1]);
            DB::selectOne("select set_config('application_name', ?, false)", [$marker]);
            fwrite($sockets[1], "ready\n");
            try {
                if ($interactionWins) {
                    DB::transaction(function () use ($source): void {
                        $locked = Commit::query()->lockForUpdate()->findOrFail($source->id);
                        new UpdateCommitRequest(['expected_version' => $source->version()])->ensureEditable($locked);
                        resolve(UpdateCommit::class)->handle($locked, 'Amended');
                    });
                } else {
                    resolve(CreateCommit::class)->handle($actor, 'Reply', parent: $quote ? null : $source, quotedCommit: $quote ? $source : null);
                }

                fwrite($sockets[1], "unexpected success\n");
            } catch (ValidationException|CommitSourceChanged) {
                fwrite($sockets[1], "blocked\n");
            }

            exit(0);
        }

        fclose($sockets[1]);
        stream_set_timeout($sockets[0], 20);
        DB::beginTransaction();
        $locked = Commit::query()->lockForUpdate()->findOrFail($source->id);
        fwrite($sockets[0], "start\n");
        throw_if(mb_trim((string) fgets($sockets[0])) !== 'ready', RuntimeException::class, 'Competing process did not start');

        for ($attempt = 0; $attempt < 200; $attempt++) {
            $waiting = (int) DB::selectOne("select count(*) as count from pg_stat_activity where application_name = ? and wait_event_type = 'Lock'", [$marker])->count > 0;
            if ($waiting) {
                break;
            }

            Sleep::usleep(10000);
        }

        if (! $waiting) {
            DB::rollBack();
            throw new RuntimeException('Competing process did not wait for the row lock');
        }

        if ($interactionWins) {
            resolve(CreateCommit::class)->handle($actor, 'Reply', parent: $quote ? null : $locked, quotedCommit: $quote ? $locked : null);
        } else {
            new UpdateCommitRequest(['expected_version' => $source->version()])->ensureEditable($locked);
            resolve(UpdateCommit::class)->handle($locked, 'Amended');
        }

        DB::commit();
        $result = mb_trim((string) fgets($sockets[0]));
        pcntl_waitpid($pid, $status);
        throw_if($result !== 'blocked' || $source->refresh()->body !== ($interactionWins ? 'Original' : 'Amended'), RuntimeException::class, 'Unexpected race outcome: '.$result);

        DB::table('commits')->whereIn('user_id', [$author->id, $actor->id])->delete();
        DB::table('users')->whereIn('id', [$author->id, $actor->id])->delete();
        fclose($sockets[0]);
        echo ($quote ? 'Quote' : 'Reply').' / '.($interactionWins ? 'interaction' : 'amendment')." wins: passed\n";
    }
}

$author = User::factory()->create();
config(['app.admins' => [$author->username], 'cache.default' => 'database']);
$lock = Cache::lock(UniqueLock::getKey(new CheckUserLevel($author)), 60);
throw_unless($lock->get(), RuntimeException::class, 'Could not prepare unique-job collision');
try {
    $commit = resolve(CreateCommit::class)->handle($author, 'Database-cache collision check');
    throw_unless($commit->fresh() instanceof Commit, RuntimeException::class, 'Commit was rolled back by a unique-job collision');
    $pending = Commit::factory()->for($author)->pending()->create();
    resolve(UpdateCommitStatuses::class)->handle([$pending->id], CommitStatus::Approved);
    throw_unless($pending->fresh()->status === CommitStatus::Approved, RuntimeException::class, 'Approval was rolled back by a unique-job collision');
    echo "Database-cache unique-job collisions: passed\n";
} finally {
    $lock->release();
    DB::table('commits')->where('user_id', $author->id)->delete();
    DB::table('users')->where('id', $author->id)->delete();
}
