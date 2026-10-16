<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\CreateCommitPreviewRequest;
use App\Services\CodeSpans;
use App\Services\EmojiShortcodes;
use Illuminate\Http\JsonResponse;

final readonly class CommitPreviewController
{
    /**
     * Highlighting runs on the server only, so the composer asks here to preview a draft with code blocks.
     */
    public function store(CreateCommitPreviewRequest $request, EmojiShortcodes $emojiShortcodes, CodeSpans $codeSpans): JsonResponse
    {
        return response()->json([
            'segments' => $codeSpans->highlight($emojiShortcodes->replace($request->string('body')->value())),
        ]);
    }
}
