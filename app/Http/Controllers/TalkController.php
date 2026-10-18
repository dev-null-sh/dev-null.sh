<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\CreateTalk;
use App\Actions\DeleteTalk;
use App\Http\Requests\CreateTalkRequest;
use App\Http\Requests\DeleteTalkRequest;
use App\Http\Requests\ListTalkRequest;
use App\Http\Requests\ShowTalkRequest;
use App\Models\Commit;
use App\Models\Talk;
use App\Models\User;
use App\Queries\CommitRepliesQuery;
use App\Services\PageMetadata;
use Carbon\CarbonImmutable;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

final readonly class TalkController
{
    public function index(ListTalkRequest $request, #[CurrentUser] User $user): Response
    {
        return Inertia::render('talks/index', [
            'talks' => fn (): LengthAwarePaginator => Talk::query()
                ->where('user_id', $user->id)
                ->with('commit')
                ->latest('presented_on')
                ->latest('id')
                ->paginate(20)
                ->through(fn (Talk $talk): array => $this->summary($talk)),
        ]);
    }

    public function store(CreateTalkRequest $request, #[CurrentUser] User $user, CreateTalk $action): RedirectResponse
    {
        $talk = $action->handle(
            $user,
            $request->string('title')->value(),
            $request->string('description')->value(),
            CarbonImmutable::parse($request->string('presented_on')->value()),
            $request->string('link_url')->value(),
            $request->linkKind(),
            $request->filled('event_name') ? $request->string('event_name')->value() : null,
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Talk published. It will appear in the log once it is approved.',
        ]);

        return to_route('talk.show', [$user, $talk]);
    }

    public function show(
        ShowTalkRequest $request,
        #[CurrentUser] ?User $currentUser,
        User $user,
        Talk $talk,
        CommitRepliesQuery $replies,
        PageMetadata $metadata,
    ): Response {
        $commit = $talk->commit;
        assert($commit instanceof Commit);

        $threadReplies = null;
        $getReplies = function () use (&$threadReplies, $replies, $commit, $currentUser): CursorPaginator {
            return $threadReplies ??= $replies->paginate($commit, $currentUser);
        };

        $talk->setRelation('user', $user);

        return Inertia::render('talk/show', [
            'talk' => fn (): array => $this->details($talk),
            'commit' => fn (): Commit => Commit::query()
                ->withVisibleReplyCount($currentUser)
                ->withCardDataFor($currentUser)
                ->findOrFail($commit->id),
            'replies' => $getReplies,
        ])->withViewData([
            'preview' => $currentUser instanceof User || $request->inertia() ? null : $metadata->talk($talk),
        ]);
    }

    public function destroy(DeleteTalkRequest $request, #[CurrentUser] User $user, Talk $talk, DeleteTalk $action): RedirectResponse
    {
        $action->handle($talk);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Talk deleted.',
        ]);

        return to_route('talk.index');
    }

    /**
     * @return array<string, mixed>
     */
    private function summary(Talk $talk): array
    {
        return [
            'id' => $talk->id,
            'title' => $talk->title,
            'description' => $talk->description,
            'presented_on' => $talk->presented_on->toDateString(),
            'link_url' => $talk->link_url,
            'link_kind' => $talk->link_kind->value,
            'event_name' => $talk->event_name,
            'published_at' => $talk->published_at?->toIso8601String(),
            'commit_status' => $talk->commit?->status->value,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function details(Talk $talk): array
    {
        return [
            ...$this->summary($talk),
            'user' => $talk->user,
            'commit_id' => $talk->commit_id,
        ];
    }
}
