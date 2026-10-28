<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\CreateCommitPreviewRequest;
use App\Http\Requests\ShowCommitPreviewRequest;
use App\Models\Commit;
use App\Models\User;
use App\Queries\CommitPreviewQuery;
use App\Queries\MentionedOrganizationsQuery;
use App\Queries\MentionedUsersQuery;
use App\Services\CodeSpans;
use App\Services\EmojiShortcodes;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

final readonly class CommitPreviewController
{
    public function show(ShowCommitPreviewRequest $request, #[CurrentUser] ?User $currentUser, User $user, Commit $commit, CommitPreviewQuery $preview): JsonResponse
    {
        return response()->json([
            'commit' => $preview->get($commit, $user, $currentUser),
        ])->header('Cache-Control', 'private, no-store');
    }

    /**
     * Highlighting and finding who a mention points to run on the server only, so the composer asks here to preview a
     * draft with code blocks or mentions.
     */
    public function store(
        CreateCommitPreviewRequest $request,
        EmojiShortcodes $emojiShortcodes,
        CodeSpans $codeSpans,
        MentionedUsersQuery $mentionedUsers,
        MentionedOrganizationsQuery $mentionedOrganizations,
    ): JsonResponse {
        $body = $request->string('body')->value();
        $mentions = $mentionedUsers->get($body);

        return response()->json([
            'segments' => $codeSpans->highlight($emojiShortcodes->replace($body)),
            'mentions' => $mentions,
            'organization_mentions' => $mentionedOrganizations->get($body, $mentions),
        ]);
    }
}
