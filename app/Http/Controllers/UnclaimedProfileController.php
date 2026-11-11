<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\ShowUnclaimedProfileRequest;
use App\Http\Resources\ShownStreams;
use App\Models\Profile;
use App\Models\User;
use App\Queries\UnclaimedProfileMentionsQuery;
use App\Services\PageMetadata;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Pagination\CursorPaginator;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

final readonly class UnclaimedProfileController
{
    /**
     * A developer looked up from GitHub who isn't on /dev/null yet has a page at their "@handle", with what GitHub
     * shows of them but where they are, for anyone with the link, but kept out of search engines, with how many members
     * wait to follow them and, like an organization's, the commits that mention them. Their own Commits tab stays
     * empty until signing in as them makes the page theirs. Like users' pages, it moves for good to the handle's own case.
     */
    public function show(ShowUnclaimedProfileRequest $request, #[CurrentUser] ?User $currentUser, UnclaimedProfileMentionsQuery $mentions, PageMetadata $metadata): RedirectResponse|Response
    {
        $profile = $request->profile();
        assert($profile instanceof Profile);

        if ($request->route('user') !== $profile->handle) {
            // The raw query, since Symfony's normalized one keeps only the last value of a repeated key...
            $query = $request->server->getString('QUERY_STRING');

            return redirect(route('user.show', $profile->handle).($query === '' ? '' : "?{$query}"), 301, ['Cache-Control' => 'max-age=86400, private']);
        }

        $waiting = DB::table('pending_follows')->where('github_id', $profile->github_id);

        return Inertia::render('profile/unclaimed', [
            'profile' => $profile->only(['id', 'handle', 'name', 'avatar_url', 'bio']),
            'tab' => $request->tab(...),
            'commits' => Inertia::scroll(fn (): CursorPaginator => $request->tab() === 'mentions'
                ? ShownStreams::page($mentions->builder($profile, $currentUser)->cursorPaginate(20)->appends(['tab' => 'mentions']))
                : new CursorPaginator([], 20, options: ['path' => $request->url()])),
            'waitingCount' => fn (): int => $waiting->clone()->count(),
            'isWaiting' => fn (): bool => $currentUser instanceof User && $waiting->clone()->where('user_id', $currentUser->id)->exists(),
            // Who follows whom is for members, so only they see who is waiting, among the people they follow...
            'waitingYouFollow' => fn (): array => $currentUser instanceof User ? $currentUser->following()
                ->whereIn('users.id', $waiting->clone()->select('user_id'))
                ->orderBy('users.id')
                ->limit(3)
                ->get()
                ->map(fn (User $user): array => $user->only(['id', 'handle', 'name', 'avatar_url']))
                ->all() : [],
        ])->withViewData(['preview' => $currentUser instanceof User || $request->inertia() ? null : $metadata->unclaimedProfile($profile)]);
    }
}
