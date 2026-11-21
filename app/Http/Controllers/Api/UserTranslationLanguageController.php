<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\UpdateUserTranslationLanguage;
use App\Http\Requests\UpdateApiUserTranslationLanguageRequest;
use App\Http\Resources\CurrentUserResource;
use App\Models\User;
use App\Queries\NotificationGroupsQuery;
use Illuminate\Container\Attributes\CurrentUser;

final readonly class UserTranslationLanguageController
{
    /**
     * Saves the language commits are translated into, as Settings does on the web, and answers as GET /api/v1/me does.
     */
    public function update(UpdateApiUserTranslationLanguageRequest $request, #[CurrentUser] User $user, UpdateUserTranslationLanguage $action, NotificationGroupsQuery $notifications): CurrentUserResource
    {
        $action->handle($user, $request->language());

        return CurrentUserResource::make($user->loadCounts())->additional([
            'unread_notifications_count' => $notifications->unreadCount($user),
        ]);
    }
}
