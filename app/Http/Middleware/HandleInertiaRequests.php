<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Commit;
use App\Models\User;
use Illuminate\Http\Request;
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
        $isAdmin = $request->user()?->isAdmin() ?? false;

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => fn (): ?User => $request->user()?->loadCounts(),
                'isAdmin' => $isAdmin,
                'unreadNotificationsCount' => fn (): int => $request->user()?->notifications()->relevant()->unread()->count() ?? 0,
            ],
            'hasUnmoderatedCommits' => fn (): bool => $isAdmin && Commit::query()->pending()->exists(),
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }
}
