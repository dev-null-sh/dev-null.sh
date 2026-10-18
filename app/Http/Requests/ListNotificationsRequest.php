<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Cookie;

final class ListNotificationsRequest extends FormRequest
{
    public const string TYPE_RULE = 'regex:/\A(?:all|none|(?:replies|quotes|stars|mentions|follows|moderation)(?:,(?:replies|quotes|stars|mentions|follows|moderation))*)\z/';

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
        $cookie = 'notification_types_'.$user->id;

        if ($this->has('type')) {
            $type = $this->filled('type') ? $this->string('type')->value() : 'all';
            Cookie::queue($cookie, $type, 60 * 24 * 365);

            return $type;
        }

        $type = $this->cookie($cookie, 'all');

        return is_string($type) && preg_match(mb_substr(self::TYPE_RULE, 6), $type) === 1 ? $type : 'all';
    }

    public function through(): ?CarbonImmutable
    {
        return $this->date('through')?->toImmutable();
    }
}
