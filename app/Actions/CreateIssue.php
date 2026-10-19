<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\User;
use App\Services\GitHubApp;
use Illuminate\Support\Str;

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

        return $this->github->createIssue(
            Str::limit(mb_trim(Str::before($body, "\n")), 256, '') ?: ucfirst($type).' report',
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
