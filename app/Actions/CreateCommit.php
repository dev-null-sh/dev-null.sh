<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\CommitStatus;
use App\Jobs\UnfurlLink;
use App\Models\Commit;
use App\Models\LinkPreview;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

final readonly class CreateCommit
{
    public function __construct(
        private NotifyAboutReplies $notifyAboutReplies,
        private FindFirstUrl $findFirstUrl,
        private FindTags $findTags,
    ) {
        //
    }

    public function handle(User $user, string $body, ?Commit $parent = null): Commit
    {
        return DB::transaction(function () use ($user, $body, $parent): Commit {
            $linkPreview = $this->linkPreviewFor($body);

            $commit = $user->commits()->create([
                'body' => $body,
                'parent_id' => $parent?->id,
                'status' => $user->isAdmin() ? CommitStatus::Approved : CommitStatus::Pending,
                'link_preview_id' => $linkPreview?->id,
            ]);

            $commit->tags()->attach($this->tagsFor($body));

            $this->notifyAboutReplies->handle($commit);

            if ($linkPreview instanceof LinkPreview && $linkPreview->fetched_at === null) {
                dispatch(new UnfurlLink($linkPreview));
            }

            return $commit;
        });
    }

    /**
     * Commits that link to the same URL share one preview, so a page is fetched once however often it is linked.
     */
    private function linkPreviewFor(string $body): ?LinkPreview
    {
        $url = $this->findFirstUrl->handle($body);

        if ($url === null) {
            return null;
        }

        return LinkPreview::query()->firstOrCreate(
            ['url_hash' => hash('sha256', $url)],
            ['url' => $url],
        );
    }

    /**
     * Creates the tags that were never used before in one query, then loads them all in another.
     *
     * @return Collection<int, Tag>
     */
    private function tagsFor(string $body): Collection
    {
        $names = $this->findTags->handle($body);

        if ($names === []) {
            return new Collection();
        }

        Tag::query()->upsert(array_map(fn (string $name): array => ['name' => $name], $names), ['name']);

        return Tag::query()->whereIn('name', $names)->get();
    }
}
