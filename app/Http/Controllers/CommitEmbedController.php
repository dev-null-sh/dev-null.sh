<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Dto\EmbedOptions;
use App\Http\Resources\ShownStreams;
use App\Models\Commit;
use App\Queries\CommitEmbedQuery;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * The card another site frames to show a commit. It is served without a session, so it always shows what a guest sees
 * and never sets a cookie, and its URL is signed, so only members create one.
 */
final readonly class CommitEmbedController
{
    /**
     * The route skips the web group, and its implicit bindings with it, so the commit is looked up here, deleted ones
     * included, since they show as tombstones.
     */
    public function show(Request $request, string $commit, CommitEmbedQuery $query): Response
    {
        $commit = Commit::query()->withTrashed()->findOrFail($commit);
        $options = EmbedOptions::fromRequest($request);

        Inertia::setRootView('embed');

        $tree = $query->get($commit, $options);

        $response = Inertia::render('embed/show', [
            'ancestors' => array_map(ShownStreams::embedNode(...), $tree['ancestors']),
            'focal' => ShownStreams::embedNode($tree['focal']),
            'thread' => array_map(ShownStreams::embedNode(...), $tree['thread']),
            'options' => $options->toArray(),
            'auth' => ['user' => null, 'pasteis' => null, 'organizations' => [], 'isAdmin' => false],
            'now' => now()->getTimestampMs(),
            // A tombstone names no author, its links included: they lead home instead.
            'commitUrl' => CommitEmbedQuery::tombstone($commit) === null && $commit->author !== null
                ? route('commit.show', ['profile' => $commit->author->handle, 'commit' => $commit->id])
                : url('/'),
        ])->toResponse($request);

        $response->headers->set('Content-Security-Policy', 'frame-ancestors *');
        $response->headers->set('Cache-Control', 'no-cache, private');

        return $response;
    }
}
