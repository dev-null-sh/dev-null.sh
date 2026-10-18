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
     * @return array{number: int, url: string}
     */
    public function handle(User $user, string $command, string $body): array
    {
        $type = mb_substr($command, 1);

        return $this->github->createIssue(
            Str::limit(mb_trim(Str::before($body, "\n")), 256, '') ?: ucfirst($type).' report',
            $body."\n\n---\n\nReported by @{$user->username} via [/dev/null](".route('user.show', $user).').',
            [$type === 'feature' ? 'enhancement' : 'bug'],
        );
    }
}
