<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use App\Queries\SuggestedUsersQuery;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Middleware;

final class HandleInertiaRequests extends Middleware
{
    /**
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $user?->loadCount(['commits', 'followers', 'following']),
                'isAdmin' => $user?->isAdmin() ?? false,
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            ...$user instanceof User ? [
                'suggestedUsers' => Inertia::defer(fn (): Collection => resolve(SuggestedUsersQuery::class)->get($user, limit: 6)),
            ] : [],
        ];
    }
}
