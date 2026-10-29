<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\CreatePollEndedNotifications;
use App\Models\Poll;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

#[Signature('polls:notify-ended')]
#[Description('Notify the voters of the polls that closed in the past day that the results are in')]
final class NotifyEndedPollsCommand extends Command
{
    public function handle(CreatePollEndedNotifications $action): int
    {
        $failed = false;
        $notified = 0;
        // The whole window is scanned each run — streamed with lazyById, like ssh-keys:sync — and the
        // notification key keeps re-scans duplicate-free. One missed run never loses a notification, no
        // closed poll waits behind a batch cap, and a poll stays in the window for a day after it closes.
        $polls = Poll::query()
            ->whereNotNull('opened_at')
            ->whereRaw('opened_at + make_interval(mins => duration) <= ?', [now()])
            ->whereRaw('opened_at + make_interval(mins => duration) > ?', [now()->subDay()])
            ->with('commit:id,user_id')
            ->lazyById();

        foreach ($polls as $poll) {
            try {
                $action->handle($poll);
            } catch (Throwable $exception) {
                report($exception);
                $this->error('Could not notify the voters of poll '.$poll->id.'. It will be retried.');
                $failed = true;
            }

            $notified++;
        }

        $this->components->info("Notified the voters of {$notified} closed polls.");

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
