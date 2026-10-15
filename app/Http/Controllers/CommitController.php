<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\CreateCommit;
use App\Actions\DeleteCommit;
use App\Enums\CommitStatus;
use App\Http\Requests\CreateCommitRequest;
use App\Http\Requests\DeleteCommitRequest;
use App\Http\Requests\ShowCommitRequest;
use App\Models\Commit;
use App\Models\User;
use App\Queries\CommitAncestorsQuery;
use App\Queries\CommitBuilder;
use App\Queries\CommitRepliesQuery;
use App\Services\Terminal\TerminalRenderer;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

final readonly class CommitController
{
    public function show(ShowCommitRequest $request, #[CurrentUser] ?User $currentUser, User $user, Commit $commit, CommitAncestorsQuery $ancestors, CommitRepliesQuery $replies, TerminalRenderer $renderer): RedirectResponse|Response|HttpResponse
    {
        if (TerminalRenderer::wantsCli($request)) {
            $color = TerminalRenderer::isColorSupported($request);
            $force16 = TerminalRenderer::is16Color($request);

            $commit->loadCount(['replies' => fn (CommitBuilder $query): CommitBuilder => $query->approved()])
                ->loadCount('stars')
                ->load('user');

            if (! $currentUser instanceof User) {
                return response($renderer->commitPreview($commit, $color, $force16))
                    ->header('Content-Type', 'text/plain; charset=utf-8');
            }

            /** @var Collection<int, Commit> $ancestorCollection */
            $ancestorCollection = new Collection();
            $current = $commit;

            while ($current->parent_id !== null) {
                $parent = Commit::query()
                    ->approved()
                    ->with('user')
                    ->withCount('stars')
                    ->withCount(['replies' => fn (CommitBuilder $query): CommitBuilder => $query->approved()])
                    ->find($current->parent_id);

                if (! $parent instanceof Commit) {
                    break;
                }

                $ancestorCollection->prepend($parent);
                $current = $parent;
            }

            $repliesList = $commit->replies()
                ->approved()
                ->with('user')
                ->withCount('stars')
                ->withCount(['replies' => fn (CommitBuilder $query): CommitBuilder => $query->approved()])
                ->latest()
                ->get();

            $content = $renderer->thread($commit, $ancestorCollection, $repliesList, $color, $force16);

            return response($content)->header('Content-Type', 'text/plain; charset=utf-8');
        }

        if (! $currentUser instanceof User) {
            return $this->preview($user, $commit);
        }

        $commit = Commit::query()
            ->withVisibleReplyCount($currentUser)
            ->withSavedState($currentUser)
            ->with(['user', 'linkPreview', 'images'])
            ->withStarsFor($currentUser)
            ->findOrFail($commit->id);

        return Inertia::render('commit/show', [
            'commit' => $commit,
            'ancestors' => $ancestors->get($commit, $currentUser),
            'replies' => $replies->paginate($commit, $currentUser),
        ]);
    }

    public function store(CreateCommitRequest $request, #[CurrentUser] User $user, CreateCommit $action): RedirectResponse
    {
        $commit = $action->handle($user, $request->string('body')->value(), $request->parent(), $request->images());

        Inertia::flash('committed', [
            'id' => $commit->id,
            'body' => $commit->body,
        ]);

        if ($commit->status === CommitStatus::Pending) {
            Inertia::flash('toast', [
                'type' => 'info',
                'message' => __('Your commit is pending review. Everyone will see it once it is approved.'),
            ]);
        }

        return back();
    }

    public function destroy(DeleteCommitRequest $request, Commit $commit, DeleteCommit $action): RedirectResponse
    {
        $action->handle($commit);

        if (url()->previous() === route('commit.show', [$commit->user, $commit])) {
            return to_route('user.show', $commit->user);
        }

        return back();
    }

    /**
     * Guests only get the landing page, with the commit's link preview for crawlers.
     */
    private function preview(User $user, Commit $commit): RedirectResponse|Response
    {
        redirect()->setIntendedUrl(route('commit.show', [$user, $commit]));

        if (app()->isLocal() && config('app.auto_login')) {
            return to_route('home');
        }

        $commit->loadPublicPreview();

        $title = $commit->parent instanceof Commit
            ? "{$user->nameWithHandle()} replying to @{$commit->parent->user->username} on /dev/null"
            : "{$user->nameWithHandle()} on /dev/null";

        return Inertia::render('welcome')->withViewData(['preview' => [
            'type' => 'article',
            'title' => $title,
            'description' => Str::limit(Str::squish($commit->body), 200, '…', preserveWords: true),
            'url' => route('commit.show', [$user, $commit]),
            'image' => route('commit-open-graph-image.show', ['user' => $user, 'commit' => $commit, 'v' => $commit->openGraphImageVersion()]),
        ]]);
    }
}
