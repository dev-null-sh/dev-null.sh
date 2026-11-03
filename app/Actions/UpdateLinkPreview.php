<?php

declare(strict_types=1);

namespace App\Actions;

use App\Contracts\Services\LinkPreviewProvider;
use App\Enums\CommitStatus;
use App\Enums\CommitUpdate;
use App\Enums\UserUpdate;
use App\Events\CommitsUpdated;
use App\Events\UsersUpdated;
use App\Models\Commit;
use App\Models\ConversationParticipant;
use App\Models\LinkPreview;
use App\Models\Message;
use Illuminate\Support\Collection;

final readonly class UpdateLinkPreview
{
    public function __construct(private LinkPreviewProvider $linkPreviews)
    {
        //
    }

    /**
     * Stores the Open Graph metadata of the page behind the link. A page that can't or mustn't be fetched is marked as
     * fetched without metadata, so it is not tried again. Failures that may pass are thrown, so the caller can try again
     * later. Once every attempt has failed, it is marked as fetched without metadata too.
     *
     * Every commit and message showing the link stops waiting for it: public commits on their channels, the authors
     * of the others and the people in each conversation on their own.
     */
    public function handle(LinkPreview $linkPreview, bool $hasFailed = false): void
    {
        $linkPreview->update([...$hasFailed ? [] : $this->linkPreviews->metadata($linkPreview->url), 'fetched_at' => now()]);

        $commits = Commit::query()->where('link_preview_id', $linkPreview->id)->get(['id', 'user_id', 'status']);

        CommitsUpdated::dispatch($commits->where('status', CommitStatus::Approved), CommitUpdate::Preview);
        UsersUpdated::dispatch($commits->where('status', '!==', CommitStatus::Approved)->pluck('user_id'), UserUpdate::Commits);

        ConversationParticipant::query()
            ->whereIn('conversation_id', Message::query()->where('link_preview_id', $linkPreview->id)->select('conversation_id'))
            ->get(['conversation_id', 'user_id'])
            ->groupBy('conversation_id')
            ->each(fn (Collection $participants, string $conversationId) => UsersUpdated::dispatch($participants->pluck('user_id'), UserUpdate::Conversation, ['conversation' => $conversationId]));
    }
}
