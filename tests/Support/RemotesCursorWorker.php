<?php

declare(strict_types=1);

use App\Actions\CreateMessage;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Sleep;

require __DIR__.'/../../vendor/autoload.php';

$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

[$script, $senderId, $conversationId, $mode, $ready, $release, $finished, $tag] = $argv;

DB::select('select set_config(?, ?, false)', ['application_name', $tag]);

if ($mode === 'hold') {
    DB::listen(static function (QueryExecuted $query) use ($ready, $release): void {
        if (! str_contains($query->sql, 'insert into "messages"')) {
            return;
        }

        file_put_contents($ready, 'inserted');
        $deadline = microtime(true) + 30;

        while (! is_file($release)) {
            throw_if(microtime(true) >= $deadline, RuntimeException::class, 'Timed out waiting to release the first insert.');

            Sleep::usleep(20_000);
        }
    });
}

try {
    $sender = User::query()->findOrFail($senderId);
    $conversation = Conversation::query()->findOrFail($conversationId);

    $message = resolve(CreateMessage::class)->handle(
        $sender,
        $conversation,
        'concurrent cursor probe',
        $mode === 'hold' ? '96c98d0d-e42e-40aa-b7d2-42490573a016' : '96c98d0d-e42e-40aa-b7d2-42490573a017',
        linkPreview: false,
    );

    file_put_contents($finished, (string) $message->id);
} catch (Throwable $throwable) {
    fwrite(STDERR, $throwable->__toString().PHP_EOL);
    exit(1);
}
