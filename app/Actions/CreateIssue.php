<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\User;
use App\Services\GitHubApp;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

final readonly class CreateIssue
{
    public function __construct(private GitHubApp $github, private CreateCommitImageFiles $createImageFiles)
    {
        //
    }

    /**
     * The issue is opened by the app, crediting the reporter, whose GitHub login is their username here.
     * GitHub cannot take uploads from an app, so images are kept here and linked from the body, and are erased again if the issue is not opened.
     *
     * @param  list<UploadedFile>  $images
     * @param  array<int, string|null>  $descriptions
     * @return array{number: int, url: string}
     */
    public function handle(User $user, string $command, string $body, array $images = [], array $descriptions = []): array
    {
        $type = mb_substr($command, 1);
        $files = $this->createImageFiles->handle($images, $descriptions, 'issue-images');

        $markdown = collect($files)
            ->map(fn (array $file): string => '!['.str_replace(['[', ']', "\n"], ' ', $file['description'] ?? 'Image').']('.route('issue-image.show', pathinfo($file['path'], PATHINFO_FILENAME)).')')
            ->implode("\n\n");

        try {
            return $this->github->createIssue(
                Str::limit(mb_trim(Str::before($body, "\n")), 256, '') ?: ucfirst($type).' report',
                mb_trim($body."\n\n".$markdown)."\n\n---\n\nReported by @{$user->username} via [/dev/null](".route('user.show', $user).').',
                [$type === 'feature' ? 'enhancement' : 'bug'],
            );
        } catch (Throwable $e) {
            Storage::disk()->delete(array_column($files, 'path'));

            throw $e;
        }
    }
}
