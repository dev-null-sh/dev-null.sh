<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\ShowUnclaimedProfileRequest;
use App\Models\Profile;
use App\Models\User;
use App\Queries\PendingFollowsQuery;
use App\Queries\UnclaimedProfileMentionCountQuery;
use App\Services\PageMetadata;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

final readonly class UnclaimedProfileController
{
    /**
     * A developer looked up from GitHub who isn't on /dev/null yet has a page at their "@handle", with what GitHub
     * shows of them but where they are, for anyone with the link, but kept out of search engines, with how many members
     * wait to follow them, and how many commits mention them. Signing in as them makes it theirs. Like users' pages, it moves for good to the handle's own case.
     */
    public function show(ShowUnclaimedProfileRequest $request, #[CurrentUser] ?User $currentUser, PageMetadata $metadata, UnclaimedProfileMentionCountQuery $mentions, PendingFollowsQuery $pendingFollows): RedirectResponse|Response
    {
        $profile = $request->profile();
        assert($profile instanceof Profile);

        if ($request->route('user') !== $profile->handle) {
            // The raw query, since Symfony's normalized one keeps only the last value of a repeated key...
            $query = $request->server->getString('QUERY_STRING');

            return redirect(route('user.show', $profile->handle).($query === '' ? '' : "?{$query}"), 301, ['Cache-Control' => 'max-age=86400, private']);
        }

        return Inertia::render('profile/unclaimed', [
            'profile' => $profile->only(['id', 'handle', 'name', 'avatar_url', 'bio']),
            // Counting reads through the commits, so the page doesn't wait for it...
            'mentionCount' => Inertia::defer(fn (): int => $mentions->get($profile)),
            'waitingCount' => fn (): int => $pendingFollows->count($profile),
            'isWaiting' => fn (): bool => $currentUser instanceof User && $pendingFollows->isWaiting($profile, $currentUser),
            'waitingYouFollow' => fn (): array => $currentUser instanceof User ? $pendingFollows->youFollow($profile, $currentUser) : [],
        ])->withViewData(['preview' => $currentUser instanceof User || $request->inertia() ? null : $metadata->unclaimedProfile($profile)]);
    }
}
