<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\RepositoryMirror;
use App\Models\RepositoryMirrorEvent;
use App\Models\User;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use SensitiveParameter;

final readonly class GitHubRepositoryMirror
{
    public function __construct(private GitHub $github) {}

    /** @return array{repository: string, repository_id: string, branch: string, private: bool} */
    public function verify(User $user, string $repository, #[SensitiveParameter] string $token): array
    {
        if (config('repository-mirrors.demo')) {
            throw ValidationException::withMessages(['repository' => 'Connections are simulated in this local demo. No GitHub requests are allowed.']);
        }
        try {
            $request = $this->github->request($token)->withoutRedirecting();
            $identity = $request->get('user')->throw()->json('id');
            assert(is_int($identity));
            if ((string) $identity !== $user->github_id) {
                throw ValidationException::withMessages(['token' => 'Use a token from the GitHub account you signed in with.']);
            }
            /** @var array{id: int, full_name: string, default_branch: string, private: bool, archived: bool, disabled: bool, permissions: array{push: bool}} $repo */
            $repo = $request->get("repos/{$repository}")->throw()->json();
            if (! $repo['permissions']['push'] || $repo['archived'] || $repo['disabled']) {
                throw ValidationException::withMessages(['repository' => 'Choose an active repository you can write to.']);
            }
            $branch = $repo['default_branch'];
            // Require an initialized repository, without making a test commit.
            $request->get("repos/{$repository}/branches/".rawurlencode($branch))->throw();

            return ['repository' => $repo['full_name'], 'repository_id' => (string) $repo['id'], 'branch' => $branch, 'private' => $repo['private']];
        } catch (ConnectionException|RequestException) {
            throw ValidationException::withMessages(['repository' => 'Could not verify this repository. Check the name, initialize it with a README, and grant this token Contents: read and write for this repository.']);
        }
    }

    public function deliver(RepositoryMirror $mirror, RepositoryMirrorEvent $event): void
    {
        if (config('repository-mirrors.demo')) {
            throw new RuntimeException('Mirroring to GitHub is disabled in the local demo.');
        }
        $request = $this->github->request($mirror->token)->withoutRedirecting();
        $base = 'repos/'.$mirror->repository;
        /** @var array{id: int, private: bool} $repo */
        $repo = $request->get($base)->throw()->json();
        if ((string) $repo['id'] !== $mirror->repository_id || $repo['private'] !== $mirror->private) {
            throw new RuntimeException('The repository identity or visibility changed. Reconnect it in Settings.');
        }
        /** @var array{event_id: string, type: string, actor: array{github_id: string}, commit: array{id: string}} $payload */
        $payload = ['event_id' => $event->id, ...$event->payload];
        $kind = str_starts_with($payload['type'], 'reaction.') ? 'reactions' : 'commits';
        $path = 'dev-null/'.$payload['actor']['github_id'].'/'.$kind.'/'.$payload['commit']['id'].'/'.$event->id.'.json';
        $content = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n";
        $endpoint = $base.'/contents/'.$path;
        $existing = $request->get($endpoint, ['ref' => $mirror->branch]);
        if ($existing->successful()) {
            // Recovery after GitHub accepted a write but the worker missed its response.
            $encoded = $existing->json('content');
            if (! is_string($encoded) || base64_decode($encoded, true) !== $content) {
                throw new RuntimeException('A mirror event path was changed in GitHub. Resolve the conflict before retrying.');
            }

            return;
        }
        if (! $existing->notFound()) {
            $existing->throw();
        }
        $request->put($endpoint, [
            'message' => 'dev-null: '.$payload['type'].' '.$event->id,
            'content' => base64_encode($content),
            'branch' => $mirror->branch,
        ])->throw();
    }
}
