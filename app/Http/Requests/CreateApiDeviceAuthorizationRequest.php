<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\ApiErrorCode;
use App\Exceptions\ApiException;
use App\Services\SignedRequests;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\RateLimiter;
use InvalidArgumentException;

/**
 * A terminal starts signing in, signed with the key it asks to approve. The key isn't approved yet, so only the
 * signature itself is checked here.
 */
final class CreateApiDeviceAuthorizationRequest extends FormRequest
{
    private const int SignInsPerKeyPerMinute = 5;

    private string $fingerprint = '';

    /**
     * Once the signature checks out, each key may start a few sign-ins a minute, so no key fills the cache with codes.
     * Terminals behind one address would throttle each other if it went by address, which the route limits before
     * any signature is checked.
     */
    public function authorize(SignedRequests $signedRequests): bool
    {
        try {
            $this->fingerprint = $signedRequests->verifySignature($this)->fingerprint();
        } catch (InvalidArgumentException $invalidArgumentException) {
            throw new ApiException(ApiErrorCode::InvalidSignature, $invalidArgumentException->getMessage());
        }

        $key = "device-authorizations:{$this->fingerprint}";

        throw_if(RateLimiter::tooManyAttempts($key, self::SignInsPerKeyPerMinute), fn (): ApiException => new ApiException(ApiErrorCode::RateLimited, __('Too Many Attempts.'), [
            'Retry-After' => RateLimiter::availableIn($key),
        ]));

        RateLimiter::hit($key);

        return true;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'device_name' => ['required', 'string', 'max:100'],
        ];
    }

    public function fingerprint(): string
    {
        return $this->fingerprint;
    }
}
