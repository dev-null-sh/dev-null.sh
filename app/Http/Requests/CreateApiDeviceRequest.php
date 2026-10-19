<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Dto\AuthorizationCode;
use App\Enums\ApiErrorCode;
use App\Exceptions\ApiException;
use App\Models\User;
use App\Services\AppleIdentityTokens;
use App\Services\SignedRequests;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use InvalidArgumentException;

/**
 * The devnull app redeems the code the web handed it for a device, signed with the key it asked to approve. The key
 * isn't approved yet, so only the signature itself is checked here, and the code has to be one that was handed out for
 * that key.
 *
 * Instead of a code, the app may send a Sign in with Apple token, for the account the user linked that Apple ID to.
 * Apple never creates an account: an Apple ID nobody linked is a 404 that says to sign in with GitHub first.
 */
final class CreateApiDeviceRequest extends FormRequest
{
    private const int RedemptionsPerKeyPerMinute = 12;

    private string $fingerprint = '';

    private ?AuthorizationCode $authorizationCode = null;

    private ?User $appleUser = null;

    /**
     * Once the signature checks out, each key may try a few times a minute. Phones behind one address would throttle
     * each other if it went by address, which the route limits before any signature is checked.
     */
    public function authorize(SignedRequests $signedRequests): bool
    {
        try {
            $this->fingerprint = $signedRequests->verifySignature($this)->fingerprint();
        } catch (InvalidArgumentException $invalidArgumentException) {
            throw new ApiException(ApiErrorCode::InvalidSignature, $invalidArgumentException->getMessage());
        }

        $key = "devices:{$this->fingerprint}";

        throw_if(RateLimiter::tooManyAttempts($key, self::RedemptionsPerKeyPerMinute), fn (): ApiException => new ApiException(ApiErrorCode::RateLimited, __('Too Many Attempts.'), [
            'Retry-After' => RateLimiter::availableIn($key),
        ]));

        RateLimiter::hit($key);

        return true;
    }

    /**
     * The verifier is the one the app made its PKCE challenge from (RFC 7636).
     *
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'code' => ['required_without:apple_identity_token', 'prohibits:apple_identity_token', 'string', 'max:255'],
            'code_verifier' => ['required_with:code', 'string', 'regex:/^[A-Za-z0-9._~-]{43,128}$/'],
            'apple_identity_token' => ['required_without:code', 'string', 'max:4096'],
            'nonce' => ['required_with:apple_identity_token', 'string', 'max:255'],
            'device_name' => ['required_with:apple_identity_token', 'string', 'max:100'],
        ];
    }

    public function fingerprint(): string
    {
        return $this->fingerprint;
    }

    /**
     * The user who linked the Apple ID the request signs in with, when it signs in with Apple rather than a code.
     */
    public function appleUser(): ?User
    {
        return $this->appleUser;
    }

    public function authorizationCode(): AuthorizationCode
    {
        assert($this->authorizationCode instanceof AuthorizationCode);

        return $this->authorizationCode;
    }

    /**
     * Whatever is wrong with the code, it gets the same answer: one that is unknown, expired or used, handed out for
     * another key, whose challenge the verifier doesn't match, or whose user was deleted since.
     */
    public function passedValidation(): void
    {
        if ($this->filled('apple_identity_token')) {
            $this->appleUser = $this->findAppleUser();

            return;
        }

        $key = AuthorizationCode::keyFor($this->string('code')->value());
        $stored = Cache::get($key);
        $code = is_array($stored) ? AuthorizationCode::fromArray($key, $stored) : null;

        throw_unless(
            $code instanceof AuthorizationCode
                && hash_equals($code->fingerprint, $this->fingerprint)
                && hash_equals($code->codeChallenge, AuthorizationCode::challengeFor($this->string('code_verifier')->value()))
                && User::query()->whereKey($code->userId)->exists(),
            ApiException::invalidGrant(),
        );

        $this->authorizationCode = $code;
    }

    private function findAppleUser(): User
    {
        try {
            $appleId = resolve(AppleIdentityTokens::class)->verify($this->string('apple_identity_token')->value(), $this->string('nonce')->value());
        } catch (InvalidArgumentException) {
            throw ApiException::invalidAppleToken();
        }

        $user = User::query()->where('apple_id', $appleId)->first();

        throw_unless($user instanceof User, ApiException::class, ApiErrorCode::AppleNotLinked, __('No /dev/null account is linked to this Apple ID. Sign in with GitHub, then link Apple in settings.'));

        return $user;
    }
}
