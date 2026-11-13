<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Dto\EmbedOptions;
use App\Http\Requests\ShowCommitEmbedLinkRequest;
use App\Models\Commit;
use App\Support\EmbedLinks;
use App\Support\EmbedSnippets;
use Illuminate\Http\JsonResponse;

/**
 * Signs the links of the card a member chose in the embed dialog, with the code to paste them. Only members get here,
 * so only they create embeds.
 */
final readonly class CommitEmbedLinkController
{
    public function show(ShowCommitEmbedLinkRequest $request, Commit $commit, EmbedSnippets $snippets): JsonResponse
    {
        $options = EmbedOptions::fromRequest($request);
        $links = EmbedLinks::for($commit, $options);

        return response()->json([...$links, 'snippets' => $snippets->for($commit, $options, $links)]);
    }
}
