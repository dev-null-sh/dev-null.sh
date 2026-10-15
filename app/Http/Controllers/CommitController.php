<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\CreateCommit;
use App\Actions\DeleteCommit;
use App\Enums\CommitStatus;
use App\Http\Requests\CreateCommitRequest;
use App\Http\Requests\DeleteCommitRequest;
use App\Models\Commit;
use App\Models\User;
use App\Queries\CommitAncestorsQuery;
use App\Queries\CommitRepliesQuery;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

final readonly class CommitController
{
    public function show(#[CurrentUser] ?User $currentUser, User $user, Commit $commit, CommitAncestorsQuery $ancestors, CommitRepliesQuery $replies): RedirectResponse|Response
    {
        if (! $currentUser instanceof User) {
            return $this->preview($user, $commit);
        }

        $commit = Commit::query()
            ->visibleTo($currentUser)
            ->withVisibleReplyCount($currentUser)
            ->with('user')
            ->findOrFail($commit->id);

        return Inertia::render('commit/show', [
            'commit' => $commit,
            'ancestors' => $ancestors->get($commit, $currentUser),
            'replies' => $replies->paginate($commit, $currentUser),
        ]);
    }

    public function store(CreateCommitRequest $request, #[CurrentUser] User $user, CreateCommit $action): RedirectResponse
    {
        $commit = $action->handle($user, $request->string('body')->value(), $request->parent());

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
        abort_unless($commit->status === CommitStatus::Approved, 404);

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
