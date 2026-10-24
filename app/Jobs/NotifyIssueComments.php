<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\CreateIssueCommentNotifications;
use App\Models\Issue;
use App\Services\GitHubApp;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;

final class NotifyIssueComments implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public const string CheckedAt = 'github-app:issue-comments:checked-at';

    /**
     * Lists the comments since the last check, with no webhook to configure, and notifies the reporters. A complete check
     * hands over from when it began, so a comment made while it ran is listed again rather than missed; one cut short
     * hands over from where it stopped, so the next carries on. Notifications are keyed by comment, so listing one twice
     * costs nothing. Without a previous check it looks back a day. Nothing is asked of GitHub until someone has opened an
     * issue here.
     */
    public function handle(GitHubApp $github, CreateIssueCommentNotifications $action): void
    {
        if (! Issue::query()->exists()) {
            return;
        }

        $startedAt = CarbonImmutable::now();
        $checkedAt = Cache::get(self::CheckedAt);

        $listing = $github->issueComments(
            is_string($checkedAt) ? CarbonImmutable::parse($checkedAt) : $startedAt->subDay(),
        );

        $action->handle($listing['comments']);

        Cache::forever(self::CheckedAt, ($listing['until'] ?? $startedAt)->toIso8601ZuluString());
    }
}
