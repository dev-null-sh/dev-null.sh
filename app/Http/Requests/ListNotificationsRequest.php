<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Actions\UpdateUserNotificationTypes;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;

final class ListNotificationsRequest extends FormRequest
{
    public const string TYPE_RULE = 'regex:/\A(?:all|none|(?:replies|quotes|stars|mentions|follows|moderation)(?:,(?:replies|quotes|stars|mentions|follows|moderation))*)\z/';

    /**
     * The kinds the user chose, kept on the user so the API counts and lists the same ones. Browsers that still have
     * the cookie this used to live in hand it over the first time they're seen.
     */
    public static function rememberedType(Request $request, User $user): string
    {
        $cookie = 'notification_types_'.$user->id;
        $type = $request->cookie($cookie);

        if ($user->notification_types === null && is_string($type) && preg_match(mb_substr(self::TYPE_RULE, 6), $type) === 1) {
            resolve(UpdateUserNotificationTypes::class)->handle($user, $type);
            Cookie::queue(Cookie::forget($cookie));
        }

        return $user->notificationTypes();
    }

    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'type' => ['nullable', 'string', self::TYPE_RULE],
            'through' => ['nullable', 'date'],
        ];
    }

    public function type(User $user): string
    {
        if ($this->has('type')) {
            $type = $this->filled('type') ? $this->string('type')->value() : 'all';
            resolve(UpdateUserNotificationTypes::class)->handle($user, $type);

            return $type;
        }

        return self::rememberedType($this, $user);
    }

    public function through(): ?CarbonImmutable
    {
        return $this->date('through')?->toImmutable();
    }
}
