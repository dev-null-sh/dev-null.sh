<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Throwable;
use UnexpectedValueException;

final readonly class GitHubFollowing
{
    /**
     * @return array{username: string, github_ids: list<string>, has_more: bool}
     */
    public function getPage(User $user, int $page): array
    {
        $account = $this->github()->get("user/{$user->github_id}")->throw()->json();

        throw_unless(is_array($account), UnexpectedValueException::class, 'GitHub returned an invalid account.');

        $ownerId = $account['id'] ?? null;

        throw_if($ownerId !== null && ((! is_int($ownerId) && ! is_string($ownerId)) || (string) $ownerId !== $user->github_id), UnexpectedValueException::class, 'GitHub returned a different account.');

        $username = $account['login'] ?? null;

        throw_if(! is_string($username) || preg_match('/^[a-zA-Z0-9][a-zA-Z0-9-]{0,38}$/D', $username) !== 1 || $page < 1, UnexpectedValueException::class, 'GitHub returned an invalid username.');

        $response = $this->github()->get("users/{$username}/following", ['per_page' => 100, 'page' => $page])->throw();
        $people = $response->json();

        throw_if(! is_array($people) || ! array_is_list($people), UnexpectedValueException::class, 'GitHub returned an invalid following list.');

        $ids = [];

        foreach ($people as $person) {
            $id = is_array($person) ? ($person['id'] ?? null) : null;

            throw_if((! is_int($id) && ! is_string($id)) || preg_match('/^[1-9][0-9]*$/D', (string) $id) !== 1, UnexpectedValueException::class, 'GitHub returned an invalid account ID.');

            $ids[] = (string) $id;
        }

        $link = $response->header('Link');

        return [
            'username' => $username,
            'github_ids' => array_values(array_unique($ids)),
            'has_more' => $link === '' ? count($people) === 100 : preg_match('/rel=["\']next["\']/', $link) === 1,
        ];
    }

    private function github(): PendingRequest
    {
        $request = Http::baseUrl('https://api.github.com')
            ->accept('application/vnd.github+json')
            ->withHeaders(['X-GitHub-Api-Version' => '2022-11-28'])
            ->connectTimeout(3)
            ->timeout(5)
            ->retry([100, 300], when: fn (Throwable $exception): bool => $exception instanceof ConnectionException
                || ($exception instanceof RequestException && $exception->response->serverError()));

        $clientId = config('services.github.client_id');
        $clientSecret = config('services.github.client_secret');

        return is_string($clientId) && $clientId !== '' && is_string($clientSecret) && $clientSecret !== ''
            ? $request->withBasicAuth($clientId, $clientSecret)
            : $request;
    }
}
