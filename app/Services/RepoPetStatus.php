<?php

declare(strict_types=1);

namespace App\Services;

use App\Jobs\RefreshRepoPet;
use Illuminate\Support\Facades\Cache;

final readonly class RepoPetStatus
{
    public function __construct(private GitHubRepoPet $github)
    {
        //
    }

    /** @param array{repository: string, repository_id: int, workflow_id: int, workflow: string} $configuration */
    public static function key(array $configuration): string
    {
        return 'repo-pet:'.hash('sha256', json_encode($configuration, JSON_THROW_ON_ERROR));
    }

    /**
     * @param  array{repository: string, repository_id: int, workflow_id: int, workflow: string}|null  $configuration
     * @return array{repository: string, workflow: string, mood: string, message: string, url: string, checked_at: string|null}|null
     */
    public function get(?array $configuration): ?array
    {
        if ($configuration === null) {
            return null;
        }

        /** @var array{mood: string, message: string, url: string, checked_at: string}|null $snapshot */
        $snapshot = Cache::get(self::key($configuration));

        if ($snapshot === null) {
            dispatch(new RefreshRepoPet($configuration));
        }

        return [
            'repository' => $configuration['repository'],
            'workflow' => $configuration['workflow'],
            ...($snapshot ?? [
                'mood' => 'sleeping',
                'message' => __('Checking the latest build.'),
                'url' => 'https://github.com/'.$configuration['repository'].'/actions',
                'checked_at' => null,
            ]),
        ];
    }

    /** @param array{repository: string, repository_id: int, workflow_id: int, workflow: string} $configuration */
    public function refresh(array $configuration): void
    {
        if (Cache::has(self::key($configuration))) {
            return;
        }

        Cache::put(self::key($configuration), $this->github->snapshot($configuration), 120);
    }

    /** @param array{repository: string, repository_id: int, workflow_id: int, workflow: string} $configuration */
    public function unavailable(array $configuration): void
    {
        Cache::put(self::key($configuration), [
            'mood' => 'sleeping',
            'message' => __('Build status is unavailable. Taking a nap.'),
            'url' => 'https://github.com/'.$configuration['repository'].'/actions',
            'checked_at' => now()->toIso8601String(),
        ], 120);
    }
}
