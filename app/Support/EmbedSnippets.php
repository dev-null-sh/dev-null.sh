<?php

declare(strict_types=1);

namespace App\Support;

use App\Dto\EmbedOptions;
use App\Models\Commit;
use App\Services\CodeSpans;

/**
 * The code the embed dialog hands out, one line per attribute so it reads without scrolling, and highlighted the way
 * code blocks in commits are, so the dialog shows it with the same component.
 */
final readonly class EmbedSnippets
{
    /**
     * The card's footer, under its scrolling thread.
     */
    public const int FOOTER = 45;

    /**
     * A frame whose color scheme differs from the card's gets an opaque backdrop, which shows around the card's rounded
     * corners on a dark page, so the frame keeps the card's.
     */
    public const string STYLE = 'border:0;max-width:100%;color-scheme:normal';

    public function __construct(private CodeSpans $codeSpans) {}

    /**
     * @param  array{card: string, link: string}  $links
     * @return array<string, array{code: string, block: array<string, string|null>}>
     */
    public function for(Commit $commit, EmbedOptions $options, array $links): array
    {
        $hash = mb_substr(str_replace('-', '', $commit->id), -7);
        $handle = $commit->author->handle ?? '';
        $height = ($options->maxHeight ?: 640) + self::FOOTER;

        $html = implode("\n", [
            '<iframe',
            '  src="'.$links['card'].'"',
            '  title="Commit '.$hash.' on /dev/null"',
            '  width="550"',
            '  height="'.$height.'"',
            '  loading="lazy"',
            '  style="'.self::STYLE.'"',
            '></iframe>',
            '<script async src="'.url('embed.js').'"></script>',
        ]);

        // Markdown renderers drop frames, so Markdown gets the commit's preview image, linked to the commit.
        $markdown = sprintf(
            '[![Commit %s by @%s on /dev/null](%s)](%s)',
            $hash,
            $handle,
            route('commit-open-graph-image.show', ['profile' => $handle, 'commit' => $commit->id]),
            route('commit.show', ['profile' => $handle, 'commit' => $commit->id]),
        );

        return [
            'html' => ['code' => $html, 'block' => $this->block($html, 'html')],
            // Highlighted as text: the Markdown grammar paints a whole image link in one color.
            'markdown' => ['code' => $markdown, 'block' => [...$this->block($markdown, 'text'), 'language' => 'markdown']],
            'link' => ['code' => $links['link'], 'block' => $this->block($links['link'], 'text')],
        ];
    }

    /**
     * @return array<string, string|null>
     */
    private function block(string $code, string $language): array
    {
        $segments = $this->codeSpans->highlight("```{$language}\n{$code}\n```");

        return collect($segments)->first(fn (array $segment): bool => $segment['type'] === 'block') ?? [
            'type' => 'block',
            'language' => $language,
            'filename' => null,
            'html' => e($code),
        ];
    }
}
