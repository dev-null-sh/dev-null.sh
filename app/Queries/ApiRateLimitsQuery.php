<?php

declare(strict_types=1);

namespace App\Queries;

use App\Actions\CreateIssue;
use App\Models\Commit;
use App\Models\User;
use App\Services\SignedRequests;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use LogicException;

/**
 * Every budget an API request counts toward, for the Rate limits guide, read from the limiters and routes that set
 * them rather than copied, so the guide changes when they do.
 */
final readonly class ApiRateLimitsQuery
{
    private const array Units = [60 => 'minute', 3600 => 'hour', 86400 => 'day'];

    /**
     * @return list<array{what: string, limit: int, per: string}>
     */
    public function get(): array
    {
        return [
            ...$this->limiter('Unsigned requests', 'api', signed: false, scope: 'per IP'),
            ...$this->limiter('Signed requests', 'api', scope: 'per person'),
            $this->row('Signatures that fail to check out', SignedRequests::FailuresPerAddressPerMinute, 60, 'per IP'),
            $this->row('Commits, replies and quotes, now or scheduled', Commit::MAX_PER_MINUTE, 60, 'shared with the web and the terminal'),
            ...$this->limiter('Commit searches', 'search'),
            ...$this->limiter('People searches, mention suggestions and profile cards', 'people'),
            ...$this->limiter('Issue suggestions', 'issue-suggestions'),
            ...$this->limiter('Stars', 'stars'),
            ...$this->limiter('Pins', 'pins'),
            ...$this->limiter('Follows', 'follows'),
            $this->route('Pastéis', 'api-commit-pastel.store'),
            $this->route('Translations', 'api-commit-translation.store'),
            ...$this->limiter('Reports', 'commit-reports'),
            $this->route('Organization claims', 'api-organization-admin.store'),
            ...$this->limiter('Syncs from GitHub', 'profile-syncs'),
            $this->route('Feature requests and bug reports', 'api-issue.store'),
            $this->row('Feature requests and bug reports opened on GitHub', CreateIssue::MAX_PER_DAY, 86400, 'shared with the web'),
            ...$this->limiter('Reading and polling remotes', 'conversations'),
            ...$this->limiter('Messages', 'messages'),
            ...$this->limiter('Pings', 'pings'),
        ];
    }

    /**
     * A named limiter's budgets, as it sets them for a signed request, or an unsigned one.
     *
     * @return list<array{what: string, limit: int, per: string}>
     */
    private function limiter(string $what, string $name, bool $signed = true, ?string $scope = null): array
    {
        $limiter = RateLimiter::limiter($name);

        throw_unless($limiter, fn (): LogicException => new LogicException("No rate limiter is named {$name}."));

        $limits = array_filter(Arr::wrap($limiter($this->request($signed))), fn (mixed $limit): bool => $limit instanceof Limit);

        return array_values(array_map(fn (Limit $limit): array => $this->row($what, $limit->maxAttempts, $limit->decaySeconds, $scope), $limits));
    }

    /**
     * The budget a route sets itself, as `throttle:attempts,minutes`.
     *
     * @return array{what: string, limit: int, per: string}
     */
    private function route(string $what, string $name): array
    {
        $throttle = collect(Route::getRoutes()->getByName($name)?->gatherMiddleware() ?? [])
            ->map(fn (mixed $middleware): array => is_string($middleware) && preg_match('/^throttle:(\d+),(\d+)/', $middleware, $match) === 1 ? [(int) $match[1], (int) $match[2]] : [])
            ->first(fn (array $throttle): bool => $throttle !== []);

        throw_unless($throttle, fn (): LogicException => new LogicException("The route {$name} sets no budget of its own."));

        return $this->row($what, $throttle[0], $throttle[1] * 60);
    }

    /**
     * @return array{what: string, limit: int, per: string}
     */
    private function row(string $what, int $limit, int $seconds, ?string $scope = null): array
    {
        $per = self::Units[$seconds] ?? "{$seconds} seconds";

        return ['what' => $what, 'limit' => $limit, 'per' => $scope === null ? $per : "{$per}, {$scope}"];
    }

    private function request(bool $signed): Request
    {
        $request = Request::create('/api/v1', server: ['REMOTE_ADDR' => '192.0.2.1']);

        if ($signed) {
            $request->setUserResolver(fn (): User => new User()->forceFill(['id' => '00000000-0000-7000-8000-000000000000']));
        }

        return $request;
    }
}
