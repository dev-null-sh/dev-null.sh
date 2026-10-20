<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\CreateCommitPreviewRequest;
use App\Queries\MentionedUsersQuery;
use App\Services\CodeSpans;
use App\Services\EmojiShortcodes;
use Illuminate\Http\JsonResponse;

final readonly class CommitPreviewController
{
    /**
     * Highlighting and finding who a mention points to run on the server only, so the composer asks here to preview a
     * draft with code blocks or mentions.
     */
    public function store(
        CreateCommitPreviewRequest $request,
        EmojiShortcodes $emojiShortcodes,
        CodeSpans $codeSpans,
        MentionedUsersQuery $mentionedUsers,
    ): JsonResponse {
        $body = $request->string('body')->value();

        return response()->json([
            'segments' => $codeSpans->highlight($emojiShortcodes->replace($body)),
            'mentions' => $mentionedUsers->get($body),
        ]);
    }
}
