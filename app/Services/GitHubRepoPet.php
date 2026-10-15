<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use UnexpectedValueException;

final readonly class GitHubRepoPet
{
    /**
     * @return array{repository: string, repository_id: int, workflows: list<array{id: int, name: string}>}|null
     */
    public function options(string $repository): ?array
    {
        try {
            $repo = $this->repository($repository);
            $response = $this->get("repos/{$repo['full_name']}/actions/workflows", ['per_page' => 100]);
            $workflows = $response['workflows'] ?? null;

            throw_unless(is_array($workflows) && array_is_list($workflows) && count($workflows) <= 100, UnexpectedValueException::class);

            $options = [];

            foreach ($workflows as $workflow) {
                throw_unless(is_array($workflow) && is_int($workflow['id'] ?? null) && $workflow['id'] > 0
                    && is_string($workflow['name'] ?? null) && $workflow['name'] !== '' && is_string($workflow['state'] ?? null), UnexpectedValueException::class);

                if ($workflow['state'] === 'active') {
                    $options[] = ['id' => $workflow['id'], 'name' => $workflow['name']];
                }
            }

            return ['repository' => $repo['full_name'], 'repository_id' => $repo['id'], 'workflows' => $options];
        } catch (ConnectionException|RequestException|UnexpectedValueException) {
            return null;
        }
    }

    /**
     * @param  array{repository: string, repository_id: int, workflow_id: int, workflow: string}  $configuration
     * @return array{mood: string, message: string, url: string, checked_at: string}
     */
    public function snapshot(array $configuration): array
    {
        $url = 'https://github.com';

        try {
            $repo = $this->repository($configuration['repository']);
            throw_unless($repo['id'] === $configuration['repository_id'] && $configuration['workflow_id'] > 0, UnexpectedValueException::class);
            $url = "https://github.com/{$repo['full_name']}/actions";

            $workflow = $this->get("repos/{$repo['full_name']}/actions/workflows/{$configuration['workflow_id']}");
            throw_unless(($workflow['id'] ?? null) === $configuration['workflow_id'] && ($workflow['state'] ?? null) === 'active', UnexpectedValueException::class);

            $head = $this->head($repo['full_name'], $repo['default_branch']);
            $response = $this->get("repos/{$repo['full_name']}/actions/workflows/{$configuration['workflow_id']}/runs", [
                'branch' => $repo['default_branch'], 'head_sha' => $head, 'per_page' => 100,
            ]);
            $runs = $response['workflow_runs'] ?? null;
            throw_unless(is_array($runs) && array_is_list($runs) && count($runs) <= 100, UnexpectedValueException::class);

            $latest = [];
            $latestId = 0;

            foreach ($runs as $run) {
                if (! is_array($run) || ! is_int($run['id'] ?? null) || $run['id'] <= $latestId) {
                    continue;
                }

                if (($run['workflow_id'] ?? null) !== $configuration['workflow_id'] || ($run['head_sha'] ?? null) !== $head
                    || ($run['head_branch'] ?? null) !== $repo['default_branch']
                    || ! is_string($run['event'] ?? null) || $run['event'] === '' || str_starts_with($run['event'], 'pull_request')
                    || Arr::get($run, 'repository.id') !== $repo['id'] || Arr::get($run, 'repository.private') !== false
                    || Arr::get($run, 'head_repository.id') !== $repo['id'] || Arr::get($run, 'head_repository.private') !== false) {
                    continue;
                }

                $latest = $run;
                $latestId = $run['id'];
            }

            $current = $this->repository($repo['full_name']);
            throw_unless($current['id'] === $repo['id'] && $current['default_branch'] === $repo['default_branch']
                && $this->head($current['full_name'], $current['default_branch']) === $head, UnexpectedValueException::class);

            if ($latestId === 0) {
                return $this->state('sleeping', $url);
            }

            $url = "https://github.com/{$repo['full_name']}/actions/runs/{$latestId}";
            $status = $latest['status'] ?? null;
            $conclusion = $latest['conclusion'] ?? null;
            $mood = match (true) {
                $status === 'completed' && $conclusion === 'success' => 'happy',
                $status === 'completed' && in_array($conclusion, ['failure', 'timed_out'], true) => 'grumpy',
                $conclusion === null && in_array($status, ['queued', 'in_progress', 'waiting', 'pending', 'requested'], true) => 'busy',
                default => 'sleeping',
            };

            return $this->state($mood, $url);
        } catch (ConnectionException|RequestException|UnexpectedValueException) {
            return $this->state('sleeping', $url);
        }
    }

    /**
     * @return array{id: int, full_name: string, default_branch: string}
     */
    private function repository(string $repository): array
    {
        throw_unless($this->isRepository($repository), UnexpectedValueException::class);
        $repo = $this->get("repos/{$repository}");

        throw_unless(($repo['private'] ?? null) === false && (! isset($repo['visibility']) || $repo['visibility'] === 'public')
            && is_int($repo['id'] ?? null) && $repo['id'] > 0
            && is_string($repo['full_name'] ?? null) && $this->isRepository($repo['full_name'])
            && is_string($repo['default_branch'] ?? null) && $repo['default_branch'] !== '', UnexpectedValueException::class);

        return ['id' => $repo['id'], 'full_name' => $repo['full_name'], 'default_branch' => $repo['default_branch']];
    }

    private function isRepository(string $repository): bool
    {
        return preg_match('~^[A-Za-z0-9][A-Za-z0-9-]{0,38}/(?!\.{1,2}$)[A-Za-z0-9_.-]{1,100}$~D', $repository) === 1;
    }

    private function head(string $repository, string $branch): string
    {
        $response = $this->get("repos/{$repository}/branches/".rawurlencode($branch));
        $head = Arr::get($response, 'commit.sha');
        throw_unless(($response['name'] ?? null) === $branch && is_string($head)
            && preg_match('/^(?:[a-f0-9]{40}|[a-f0-9]{64})$/D', $head) === 1, UnexpectedValueException::class);

        return $head;
    }

    /**
     * @param  array<string, int|string>  $query
     * @return array<array-key, mixed>
     */
    private function get(string $path, array $query = []): array
    {
        $request = Http::baseUrl('https://api.github.com')
            ->accept('application/vnd.github+json')
            ->withHeaders(['X-GitHub-Api-Version' => '2022-11-28'])
            ->withoutRedirecting()
            ->connectTimeout(3)
            ->timeout(5);

        $clientId = config('services.github.client_id');
        $clientSecret = config('services.github.client_secret');

        if (is_string($clientId) && $clientId !== '' && is_string($clientSecret) && $clientSecret !== '') {
            $request = $request->withBasicAuth($clientId, $clientSecret);
        }

        $response = $request->get($path, $query)
            ->throwUnlessStatus(200)
            ->json();

        throw_unless(is_array($response), UnexpectedValueException::class);

        return $response;
    }

    /**
     * @return array{mood: string, message: string, url: string, checked_at: string}
     */
    private function state(string $mood, string $url): array
    {
        return [
            'mood' => $mood,
            'message' => match ($mood) {
                'happy' => 'The latest build passed.',
                'busy' => 'Building the latest commit.',
                'grumpy' => 'The latest build needs attention.',
                default => 'No current build result is available.',
            },
            'url' => $url,
            'checked_at' => now()->toIso8601String(),
        ];
    }
}
