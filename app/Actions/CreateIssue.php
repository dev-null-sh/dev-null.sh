<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\User;
use App\Services\GitHubApp;
use Illuminate\Support\Str;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\CommonMark\Node\Block\Heading;
use League\CommonMark\Node\Node;
use League\CommonMark\Node\StringContainerHelper;
use League\CommonMark\Parser\MarkdownParser;

final readonly class CreateIssue
{
    public function __construct(private GitHubApp $github)
    {
        //
    }

    /**
     * The issue is opened by the app, crediting the reporter, whose GitHub login is their username here.
     *
     * @param  array<string, string>  $environment
     * @return array{number: int, url: string}
     */
    public function handle(User $user, string $command, string $body, array $environment = []): array
    {
        $type = mb_substr($command, 1);
        $title = mb_trim(Str::before($body, "\n"));
        $markdown = new Environment();
        $markdown->addExtension(new CommonMarkCoreExtension());

        $heading = collect([...new MarkdownParser($markdown)->parse($body)->children()])
            ->first(fn (Node $node): bool => $node instanceof Heading && $node->getLevel() === 1);

        if ($heading instanceof Heading) {
            $title = StringContainerHelper::getChildText($heading);
            $start = $heading->getStartLine();
            $end = $heading->getEndLine();
            assert(is_int($start) && is_int($end));
            $body = Str::trim(
                Str::of($body)->explode("\n")
                    ->except(range($start - 1, $end - 1))
                    ->implode("\n"),
                "\n",
            );
        }

        return $this->github->createIssue(
            Str::limit($title, 256, '') ?: ucfirst($type).' report',
            $body.$this->details($environment)."\n\n---\n\nReported by @{$user->username} via [/dev/null](".route('user.show', $user).').',
            [$type === 'feature' ? 'enhancement' : 'bug'],
        );
    }

    /**
     * Folded away, so the issue reads as written while maintainers still find how to reproduce it.
     *
     * @param  array<string, string>  $environment
     */
    private function details(array $environment): string
    {
        if ($environment === []) {
            return '';
        }

        $rows = collect($environment)
            ->map(fn (string $value, string $name): string => '| '.str_replace('_', ' ', ucfirst($name)).' | `'.str_replace(['`', '|', "\n"], ' ', $value).'` |')
            ->implode("\n");

        return "\n\n<details>\n<summary>Environment</summary>\n\n| | |\n|---|---|\n{$rows}\n\n</details>";
    }
}
