<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\CreatePollClosedNotifications;
use App\Enums\PollDuration;
use App\Models\Poll;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Date;

final class AnnounceClosedPolls implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    private const string CACHE_KEY = 'polls:announced-through';

    /**
     * Announces the polls that closed since the previous run, however late this one starts. Should the cache forget
     * when that was, the last hour is announced again, which notifies nobody twice.
     */
    public function handle(CreatePollClosedNotifications $action): void
    {
        $since = Date::createFromTimestamp(Cache::integer(self::CACHE_KEY, now()->subHour()->getTimestamp()));
        $through = now();

        Poll::query()
            ->where(function (Builder $query) use ($since, $through): void {
                foreach (PollDuration::cases() as $duration) {
                    $query->orWhere(fn (Builder $query): Builder => $query
                        ->where('duration', $duration)
                        ->where('opened_at', '>', $since->subMinutes($duration->value))
                        ->where('opened_at', '<=', $through->subMinutes($duration->value)));
                }
            })
            ->with('commit')
            ->each($action->handle(...));

        Cache::put(self::CACHE_KEY, $through->getTimestamp(), now()->addDay());
    }
}
