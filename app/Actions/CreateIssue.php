<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Issue;
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
     * The issue is opened by the app, crediting the reporter, whose GitHub login is their username here. The reporter
     * is remembered with it, so its comments reach them in their notifications.
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

        $title = Str::limit($title, 256, '') ?: ucfirst($type).' report';

        $issue = $this->github->createIssue(
            $title,
            $body.$this->details($environment)."\n\n---\n\nReported by {$user->mention()} via [/dev/null](".route('user.show', $user).').',
            [$type === 'feature' ? 'enhancement' : 'bug'],
        );

        // Remembered, so comments on the issue can notify the reporter here instead of only on GitHub. GitHub never hands
        // out a number twice in one repository, so a row that already exists is left to the reporter who opened it.
        Issue::query()->insertOrIgnore([
            'id' => Str::uuid7()->toString(),
            'user_id' => $user->id,
            'repository' => config()->string('services.github.repository'),
            'number' => $issue['number'],
            'title' => $title,
            'url' => $issue['url'],
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $issue;
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
