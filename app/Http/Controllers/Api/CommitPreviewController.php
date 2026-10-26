<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Requests\CreateApiCommitPreviewRequest;
use App\Services\CommitBodies;
use App\Services\EmojiShortcodes;
use App\Services\Mentions;
use Illuminate\Http\JsonResponse;

final readonly class CommitPreviewController
{
    /**
     * Shows a draft as the commit it would become, as the web's composer previews it: the body as it would be kept, the
     * segments the app draws it from, the excerpt a list would fold it to, and where it mentions someone. Nothing is
     * kept, so previews don't count towards the commits a user may make a minute.
     */
    public function store(CreateApiCommitPreviewRequest $request, EmojiShortcodes $emojiShortcodes, CommitBodies $commitBodies, Mentions $mentions): JsonResponse
    {
        $body = $emojiShortcodes->replace($request->string('body')->value());

        return response()->json(['data' => [
            'body' => $body,
            ...$commitBodies->segments($body),
            'mentions' => $mentions->positions($body),
        ]]);
    }
}
