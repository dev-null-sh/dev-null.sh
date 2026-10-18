<?php

declare(strict_types=1);

namespace App\Services;

use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\CommonMark\Node\Block\Heading;
use League\CommonMark\Node\StringContainerHelper;
use League\CommonMark\Parser\MarkdownParser;

final readonly class MarkdownSections
{
    /**
     * @return list<array{headings: list<string>, content: string}>
     */
    public function split(string $markdown): array
    {
        $markdown = str_replace(["\r\n", "\r"], "\n", $markdown);
        $lines = explode("\n", $markdown);
        $environment = new Environment();
        $environment->addExtension(new CommonMarkCoreExtension());

        $document = new MarkdownParser($environment)->parse($markdown);
        $sections = [];
        $headings = [];
        $start = 0;

        foreach ($document->children() as $node) {
            if (! $node instanceof Heading) {
                continue;
            }

            $headingStart = $node->getStartLine();
            $headingEnd = $node->getEndLine();
            assert(is_int($headingStart) && is_int($headingEnd));

            $sections[] = ['headings' => array_values($headings), 'content' => implode("\n", array_slice($lines, $start, $headingStart - 1 - $start))];

            $headings = array_filter($headings, fn (int $level): bool => $level < $node->getLevel(), ARRAY_FILTER_USE_KEY);
            $headings[$node->getLevel()] = StringContainerHelper::getChildText($node);
            $start = $headingEnd;
        }

        $sections[] = ['headings' => array_values($headings), 'content' => implode("\n", array_slice($lines, $start))];

        return array_values(collect($sections)
            ->filter(fn (array $section): bool => mb_trim($section['content']) !== '')
            ->map(fn (array $section): array => [...$section, 'content' => mb_trim($section['content'], "\n")])
            ->all());
    }
}
