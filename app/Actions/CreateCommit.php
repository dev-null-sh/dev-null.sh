<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\CommitStatus;
use App\Jobs\UnfurlLink;
use App\Models\Commit;
use App\Models\LinkPreview;
use App\Models\User;
use App\Services\EmojiShortcodes;
use App\Services\Urls;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

final readonly class CreateCommit
{
    public function __construct(
        private CreateReplyNotifications $createReplyNotifications,
        private CreateMentionNotifications $createMentionNotifications,
        private CreateCommitImages $createCommitImages,
        private EmojiShortcodes $emojiShortcodes,
        private Urls $urls,
    ) {
        //
    }

    /**
     * @param  list<UploadedFile>  $images
     */
    public function handle(User $user, string $body, ?Commit $parent = null, array $images = []): Commit
    {
        return DB::transaction(function () use ($user, $body, $parent, $images): Commit {
            $linkPreview = $this->linkPreviewFor($body);

            $commit = $user->commits()->create([
                'body' => $this->emojiShortcodes->replace($body),
                'parent_id' => $parent?->id,
                'status' => $user->isAdmin() ? CommitStatus::Approved : CommitStatus::Pending,
                'link_preview_id' => $linkPreview?->id,
            ]);

            $this->createCommitImages->handle($commit, $images);

            $this->createReplyNotifications->handle($commit);
            $this->createMentionNotifications->handle($commit);

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
        $url = $this->urls->first($body);

        if ($url === null) {
            return null;
        }

        return LinkPreview::query()->firstOrCreate(
            ['url_hash' => hash('sha256', $url)],
            ['url' => $url],
        );
    }
}
