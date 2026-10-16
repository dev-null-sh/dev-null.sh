<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;
use App\Queries\FollowingFeedQuery;
use Illuminate\Http\Response;

final readonly class FollowingFeedController
{
    /**
     * RSS readers cannot sign in, so the secret token in the link stands in for the session.
     */
    public function show(User $user, FollowingFeedQuery $query): Response
    {
        return response()->view('feeds.following', [
            'user' => $user,
            'commits' => $query->get($user),
        ], headers: [
            'Content-Type' => 'application/rss+xml; charset=UTF-8',
            'Cache-Control' => 'private, max-age=300',
            'X-Robots-Tag' => 'noindex, nofollow',
        ]);
    }
}
