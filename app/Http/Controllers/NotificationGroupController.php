<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\ShowNotificationGroupRequest;
use App\Models\Notification;
use App\Models\User;
use App\Queries\NotificationGroupsQuery;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

final readonly class NotificationGroupController
{
    public function show(ShowNotificationGroupRequest $request, #[CurrentUser] User $user, Notification $notification, NotificationGroupsQuery $query): JsonResponse
    {
        $members = $query->members($user, $notification, $request->type())
            ->with('commit')->latest()->latest('id')->cursorPaginate(20)->withQueryString();

        $stars = $query->commitStars($user, $members->items());

        // Expanded replies, mentions and quotes can be starred from the inbox too, so they say how starred they are.
        return response()->json($members->through(fn (Notification $member): array => $this->withCommitStars($member, $stars)));
    }

    /**
     * The notification as the inbox gets it, its commit saying how starred it is.
     *
     * @param  array<string, array{stars_count: int, starred: bool}>  $stars
     * @return array<mixed>
     */
    private function withCommitStars(Notification $notification, array $stars): array
    {
        $commit = $notification->commit;

        if ($commit === null || ! isset($stars[$commit->id])) {
            return $notification->toArray();
        }

        return [...$notification->toArray(), 'commit' => [...$commit->toArray(), ...$stars[$commit->id]]];
    }
}
