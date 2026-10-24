<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Requests\ShowNotificationGroupRequest;
use App\Http\Resources\NotificationResource;
use App\Models\Notification;
use App\Models\User;
use App\Queries\CommitBuilder;
use App\Queries\NotificationGroupsQuery;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final readonly class NotificationGroupController
{
    /**
     * Every notification in the group the given one belongs to, newest first and filtered by ?type=, as the web
     * expands a group. Listing them marks nothing as read. Each commit comes as the list sends it, with the
     * recipient's own commit a quote quotes and the images of a starred commit.
     */
    public function show(ShowNotificationGroupRequest $request, #[CurrentUser] User $user, Notification $notification, NotificationGroupsQuery $query): AnonymousResourceCollection
    {
        $members = $query->members($user, $notification, $request->type())
            ->with([
                'commit.user',
                'commit.parent' => fn (BelongsTo $query): BelongsTo => $query
                    ->where(fn (CommitBuilder $query): CommitBuilder => $query->visibleTo($user))
                    ->with('user'),
            ])
            ->latest()
            ->latest('id')
            ->cursorPaginate(20)
            ->withQueryString();

        $hydrated = $query->withStarredCommitImages($query->withQuotedCommits($user, $members));

        /** @var array<int, Notification> $items */
        $items = $hydrated->items();

        new Collection($items)->loadMissing(['commit.quotedCommit.user']);

        return NotificationResource::collection($hydrated);
    }
}
