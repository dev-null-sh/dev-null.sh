<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\User;
use App\Services\GitHub;

final readonly class UpdateRepositoryContributors
{
    private const int PerPage = 100;

    private const array Sources = [
        'issues' => 'user.id',
        'commits' => 'author.id',
        'issues/comments' => 'user.id',
        'pulls/comments' => 'user.id',
    ];

    public function __construct(private GitHub $github)
    {
        //
    }

    public function handle(): int
    {
        $repository = config()->string('services.github.repository');
        $awarded = 0;

        foreach (self::Sources as $source => $identity) {
            $page = 1;

            do {
                /** @var list<array{user?: array{id: int}|null, author?: array{id: int}|null}> $items */
                $items = $this->github->request()->get("repos/{$repository}/{$source}", [
                    'per_page' => self::PerPage,
                    'page' => $page,
                    ...($source === 'issues' ? ['state' => 'all'] : []),
                ])->throw()->json();

                $githubIds = collect($items)->pluck($identity)
                    ->filter(fn (mixed $id): bool => is_int($id))
                    ->map(fn (int $id): string => (string) $id)
                    ->unique()->values()->all();

                $awarded += User::query()
                    ->where('is_contributor', false)
                    ->whereIn('github_id', $githubIds)
                    ->update(['is_contributor' => true]);

                $page++;
            } while (count($items) === self::PerPage);
        }

        return $awarded;
    }
}
