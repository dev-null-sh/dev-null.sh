<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Models\User;
use App\Services\CommitTranslations;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Locale;

final readonly class UserSettingsController
{
    /**
     * What the web's settings show only the user: their timezone and the language commits are translated into, `null`
     * to follow the device, whether commits are translated automatically, the languages they may pick from, and
     * whether the web shows them its admin pages, which the app only links to.
     */
    public function show(#[CurrentUser] User $user, CommitTranslations $translations): JsonResponse
    {
        return response()->json(['data' => [
            'timezone' => $user->timezone,
            'show_local_time' => $user->show_local_time,
            'translation_language' => $user->translation_language,
            'automatic_translation' => $user->automatic_translation ?? true,
            'translation_languages' => collect($translations->languages())->map(fn (string $code): array => [
                'code' => $code,
                'name' => Locale::getDisplayLanguage($code, 'en'),
                'native_name' => Locale::getDisplayLanguage($code, $code),
            ])->sortBy('name')->values()->all(),
            'is_admin' => $user->isAdmin(),
        ]])->header('Cache-Control', 'private, no-store');
    }
}
