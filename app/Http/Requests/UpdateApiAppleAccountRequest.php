<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\ApiErrorCode;
use App\Exceptions\ApiException;
use App\Models\User;
use App\Services\AppleIdentityTokens;
use Illuminate\Foundation\Http\FormRequest;
use InvalidArgumentException;

/**
 * Links the Apple ID the user signed in with on the device to their account. An Apple ID can only be linked to one
 * account. The authorization code Apple handed the app along with the token is optional, and only swapped for a
 * refresh token, so the link can be revoked at Apple later.
 */
final class UpdateApiAppleAccountRequest extends FormRequest
{
    private string $appleId = '';

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'identity_token' => ['required', 'string', 'max:4096'],
            'nonce' => ['required', 'string', 'max:255'],
            'authorization_code' => ['nullable', 'string', 'max:1024'],
        ];
    }

    public function appleId(): string
    {
        return $this->appleId;
    }

    public function authorizationCode(): ?string
    {
        return $this->filled('authorization_code') ? $this->string('authorization_code')->value() : null;
    }

    public function passedValidation(): void
    {
        $user = $this->user();
        assert($user instanceof User);

        try {
            $this->appleId = resolve(AppleIdentityTokens::class)->verify($this->string('identity_token')->value(), $this->string('nonce')->value());
        } catch (InvalidArgumentException) {
            throw ApiException::invalidAppleToken();
        }

        throw_if(
            User::query()->where('apple_id', $this->appleId)->whereKeyNot($user->id)->exists(),
            ApiException::class,
            ApiErrorCode::AppleLinkedElsewhere,
            __('This Apple ID is already linked to another /dev/null account.'),
        );
    }
}
