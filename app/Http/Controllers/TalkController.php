<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\CreateTalk;
use App\Actions\DeleteTalk;
use App\Actions\PublishTalk;
use App\Http\Requests\CreateTalkRequest;
use App\Http\Requests\DeleteTalkRequest;
use App\Http\Requests\ListTalkRequest;
use App\Http\Requests\PublishTalkRequest;
use App\Http\Requests\ShowTalkRequest;
use App\Models\Commit;
use App\Models\Talk;
use App\Models\User;
use App\Queries\CommitBuilder;
use App\Queries\CommitRepliesQuery;
use App\Services\Embeds;
use App\Services\PageMetadata;
use Carbon\CarbonImmutable;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

final readonly class TalkController
{
    public function __construct(private Embeds $embeds)
    {
        //
    }

    public function index(ListTalkRequest $request, #[CurrentUser] User $user): Response
    {
        return Inertia::render('talks/index', [
            'talks' => Inertia::scroll(fn (): CursorPaginator => Talk::query()
                ->with(['user:id,username,name,avatar_url,preferred_lane,lane_color', 'commit'])
                ->where(function (Builder $query) use ($user): void {
                    $query->where(function (Builder $discoverable): void {
                        $discoverable->whereNotNull('published_at')
                            ->where('is_unlisted', false)
                            ->whereNotNull('commit_id')
                            ->whereHas('commit', fn (CommitBuilder $commit): CommitBuilder => $commit->visibleTo(null)->approved());
                    })->orWhere('user_id', $user->id);
                })
                ->orderByRaw('case when published_at is null then 0 else 1 end')
                ->latest('published_at')
                ->latest('id')
                ->cursorPaginate(20)
                ->through(fn (Talk $talk): array => [
                    ...$this->summary($talk),
                    'user' => [
                        'id' => $talk->user->id,
                        'username' => $talk->user->username,
                        'name' => $talk->user->name,
                        'avatar_url' => $talk->user->avatar_url,
                        'lane' => $talk->user->lane(),
                    ],
                ])),
        ]);
    }

    public function store(CreateTalkRequest $request, #[CurrentUser] User $user, CreateTalk $action): RedirectResponse
    {
        $talk = $action->handle(
            $user,
            $request->string('title')->value(),
            $request->string('description')->value(),
            CarbonImmutable::parse($request->string('presented_on')->value()),
            $request->filled('slides_url') ? $request->string('slides_url')->value() : null,
            $request->filled('recording_url') ? $request->string('recording_url')->value() : null,
            $request->filled('event_name') ? $request->string('event_name')->value() : null,
            $request->visibility(),
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => match (true) {
                $talk->isDraft() => 'Talk saved as a private draft.',
                $talk->is_unlisted => 'Talk published as unlisted. Only people with the link can find it.',
                default => 'Talk published. It will appear in the log once it is approved.',
            },
        ]);

        return to_route('talk.show', [$user, $talk]);
    }

    public function publish(PublishTalkRequest $request, #[CurrentUser] User $user, Talk $talk, PublishTalk $action): RedirectResponse
    {
        $talk = $action->handle($talk, $request->boolean('unlisted'));

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $talk->is_unlisted
                ? 'Talk published as unlisted.'
                : 'Talk published. It will appear in the log once it is approved.',
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

        $threadReplies = null;
        $getReplies = function () use (&$threadReplies, $replies, $commit, $currentUser): ?CursorPaginator {
            if (! $commit instanceof Commit) {
                return null;
            }

            return $threadReplies ??= $replies->paginate($commit, $currentUser);
        };

        $talk->setRelation('user', $user);

        return Inertia::render('talk/show', [
            'talk' => fn (): array => $this->details($talk),
            'commit' => fn (): ?Commit => $commit instanceof Commit
                ? Commit::query()
                    ->withVisibleReplyCount($currentUser)
                    ->withCardDataFor($currentUser)
                    ->findOrFail($commit->id)
                : null,
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
        $recordingEmbed = is_string($talk->recording_url) ? $this->embeds->for($talk->recording_url) : null;
        $slidesEmbed = is_string($talk->slides_url) ? $this->embeds->for($talk->slides_url) : null;

        return [
            'id' => $talk->id,
            'title' => $talk->title,
            'description' => $talk->description,
            'presented_on' => $talk->presented_on->toDateString(),
            'slides_url' => $talk->slides_url,
            'recording_url' => $talk->recording_url,
            'event_name' => $talk->event_name,
            'is_unlisted' => $talk->is_unlisted,
            'published_at' => $talk->published_at?->toIso8601String(),
            'commit_status' => $talk->commit?->status->value,
            'recording_embed' => $recordingEmbed?->toArray(),
            'slides_embed' => $slidesEmbed?->toArray(),
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
