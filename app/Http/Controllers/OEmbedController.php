<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Dto\EmbedOptions;
use App\Http\Requests\ShowOEmbedRequest;
use App\Support\EmbedSnippets;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The oEmbed provider for commit embeds: blogging platforms turn the link from the embed dialog's Link tab into its card.
 * Bare commit links aren't signed, so they get no card and the consumer falls back to a plain link.
 */
final readonly class OEmbedController
{
    private const int WIDTH = 550;

    public function show(ShowOEmbedRequest $request): JsonResponse
    {
        $card = $request->card();
        $options = EmbedOptions::fromRequest(Request::create($card));
        $width = min(self::WIDTH, $request->integer('maxwidth', self::WIDTH));
        $height = ($options->maxHeight ?: 640) + EmbedSnippets::FOOTER;
        $author = $request->commit()->author;

        return response()->json([
            'version' => '1.0',
            'type' => 'rich',
            'provider_name' => '/dev/null',
            'provider_url' => config()->string('app.url'),
            'author_name' => $author?->name,
            'author_url' => $author === null ? null : route('user.show', $author->handle),
            'html' => sprintf(
                '<iframe src="%s" title="%s" width="%d" height="%d" loading="lazy" style="%s"></iframe><script async src="%s"></script>',
                e($card),
                e('Commit on /dev/null'),
                $width,
                $height,
                EmbedSnippets::STYLE,
                e(url('embed.js')),
            ),
            'width' => $width,
            'height' => $height,
            'cache_age' => 3600,
        ]);
    }
}
