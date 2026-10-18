<?php

declare(strict_types=1);

use App\Actions\CreateConversation;
use App\Actions\CreateConversationEvent;
use App\Actions\CreateMessage;
use App\Actions\CreateOrg;
use App\Enums\OrgEvent;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * CreateMessage::lockForAppend locks the conversation before inserting so message ids commit in tail order
 * (ConversationThreadQuery tails `id > cursor`). These pin the lock is issued before the insert on both append paths.
 */
function queriesWhileSending(Closure $send): Collection
{
    DB::flushQueryLog();
    DB::enableQueryLog();

    try {
        $send();
    } finally {
        DB::disableQueryLog();
    }

    return collect(DB::getQueryLog())->pluck('query');
}

/**
 * @param  Collection<int, string>  $queries
 */
function assertConversationLockedBeforeMessageInsert(Collection $queries, string $because): void
{
    $lock = $queries->search(fn (string $sql): bool => str_contains($sql, '"conversations"') && str_contains($sql, 'for update'));
    $insert = $queries->search(fn (string $sql): bool => str_starts_with($sql, 'insert into "messages"'));

    expect($lock)->not->toBeFalse($because)
        ->and($insert)->not->toBeFalse('Expected an insert into the messages table.')
        ->and($lock)->toBeLessThan($insert);
}

it('locks the conversation before appending a message, so ids commit in tail order', function (): void {
    [$sender, $recipient] = User::factory()->count(2)->create()->all();
    $conversation = resolve(CreateConversation::class)->handle($sender, $recipient);

    $queries = queriesWhileSending(fn () => resolve(CreateMessage::class)
        ->handle($sender, $conversation, 'hello', Str::uuid()->toString()));

    assertConversationLockedBeforeMessageInsert(
        $queries,
        'CreateMessage must lock the conversation before inserting, so a later-committing lower id cannot slip below the thread tail cursor.',
    );
});

it('locks the conversation before recording an org event', function (): void {
    $maintainer = User::factory()->create();
    $members = User::factory()->count(2)->create();
    $org = resolve(CreateOrg::class)->handle($maintainer, 'core', $members);

    $queries = queriesWhileSending(fn () => resolve(CreateConversationEvent::class)
        ->handle($maintainer, $org, OrgEvent::Renamed, body: 'renamed'));

    assertConversationLockedBeforeMessageInsert(
        $queries,
        'CreateConversationEvent must lock the conversation before inserting, since an event is a message on the same tailed id sequence.',
    );
});
