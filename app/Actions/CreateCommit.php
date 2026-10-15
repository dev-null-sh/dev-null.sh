<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\CommitStatus;
use App\Jobs\UnfurlLink;
use App\Models\Commit;
use App\Models\LinkPreview;
use App\Models\User;
use App\Services\Embeds;
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
        private Embeds $embeds,
    ) {
        //
    }

    /**
     * @param  list<UploadedFile>  $images
     */
    public function handle(User $user, string $body, ?Commit $parent = null, array $images = []): Commit
    {
        return DB::transaction(function () use ($user, $body, $parent, $images): Commit {
            ['body' => $body, 'url' => $embedUrl, 'ratio' => $embedRatio] = $this->withoutEmbed($body);

            $linkPreview = $this->linkPreviewFor($body);

            $commit = $user->commits()->create([
                'body' => $this->emojiShortcodes->replace($body),
                'parent_id' => $parent?->id,
                'status' => $user->isAdmin() ? CommitStatus::Approved : CommitStatus::Pending,
                'link_preview_id' => $linkPreview?->id,
                'embed_url' => $embedUrl,
                'embed_ratio' => $embedRatio,
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
     * Moves the "/embed <url> [<ratio>]" command that may end the body out of it. A command that can't be embedded
     * stays in the body as text.
     *
     * @return array{body: string, url: string|null, ratio: string|null}
     */
    private function withoutEmbed(string $body): array
    {
        $embed = $this->embeds->extract($body);

        return $embed === null || $embed['url'] === null || $embed['ratio'] === null
            ? ['body' => $body, 'url' => null, 'ratio' => null]
            : $embed;
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
