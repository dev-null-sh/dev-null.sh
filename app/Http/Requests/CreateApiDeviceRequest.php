<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Dto\AuthorizationCode;
use App\Dto\DeviceAuthorization;
use App\Enums\ApiErrorCode;
use App\Enums\DeviceAuthorizationStatus;
use App\Exceptions\ApiException;
use App\Models\Device;
use App\Models\User;
use App\Services\DeviceAuthorizations;
use App\Services\SignedRequests;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use InvalidArgumentException;

/**
 * The devnull app redeems the code the web handed it for a device, signed with the key it asked to approve; or a
 * terminal polls, with the device code it was handed, for the sign-in the user decides on the web. The key isn't
 * approved yet, so only the signature itself is checked here, and the code has to be one that was handed out for
 * that key.
 */
final class CreateApiDeviceRequest extends FormRequest
{
    private const int RedemptionsPerKeyPerMinute = 12;

    private string $fingerprint = '';

    private ?AuthorizationCode $authorizationCode = null;

    private ?Device $device = null;

    /**
     * Once the signature checks out, each key may try a few times a minute. Phones behind one address would throttle
     * each other if it went by address, which the route limits before any signature is checked. A terminal polling
     * for its sign-in is paced by that sign-in instead, since it asks every few seconds by design.
     */
    public function authorize(SignedRequests $signedRequests): bool
    {
        try {
            $this->fingerprint = $signedRequests->verifySignature($this)->fingerprint();
        } catch (InvalidArgumentException $invalidArgumentException) {
            throw new ApiException(ApiErrorCode::InvalidSignature, $invalidArgumentException->getMessage());
        }

        if (! $this->filled('device_code')) {
            $key = "devices:{$this->fingerprint}";

            throw_if(RateLimiter::tooManyAttempts($key, self::RedemptionsPerKeyPerMinute), fn (): ApiException => new ApiException(ApiErrorCode::RateLimited, __('Too Many Attempts.'), [
                'Retry-After' => RateLimiter::availableIn($key),
            ]));

            RateLimiter::hit($key);
        }

        return true;
    }

    /**
     * Either the code from the web with the verifier the app made its PKCE challenge from (RFC 7636), or the device
     * code a terminal was handed (RFC 8628), never both.
     *
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'code' => ['required_without:device_code', 'prohibits:device_code', 'string', 'max:255'],
            'code_verifier' => ['required_with:code', 'string', 'regex:/^[A-Za-z0-9._~-]{43,128}$/'],
            'device_code' => ['nullable', 'required_without:code', 'string', 'max:255'],
        ];
    }

    public function authorizationCode(): AuthorizationCode
    {
        assert($this->authorizationCode instanceof AuthorizationCode);

        return $this->authorizationCode;
    }

    /**
     * The device a terminal's sign-in approved, when that is what was asked for.
     */
    public function device(): ?Device
    {
        return $this->device;
    }

    /**
     * Whatever is wrong with the code, it gets the same answer: one that is unknown, expired or used, handed out for
     * another key, whose challenge the verifier doesn't match, or whose user was deleted since.
     */
    public function passedValidation(): void
    {
        if ($this->filled('device_code')) {
            $this->device = $this->approvedDevice($this->string('device_code')->value());

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

    /**
     * The device the user approved on the web for a terminal's sign-in. Until they decide, the terminal is told to
     * wait, and to slow down when it asks again sooner than the interval it was given. A sign-in that is unknown,
     * expired or started by another key, or whose device or user is gone since, gets the one answer a bad code gets.
     */
    private function approvedDevice(string $deviceCode): Device
    {
        $authorization = resolve(DeviceAuthorizations::class)->fromDeviceCode($deviceCode);

        throw_unless($authorization instanceof DeviceAuthorization && hash_equals($authorization->fingerprint, $this->fingerprint), ApiException::invalidGrant());

        if ($authorization->status === DeviceAuthorizationStatus::Pending) {
            throw Cache::add("{$authorization->key}:polled", true, DeviceAuthorization::INTERVAL_SECONDS - 1)
                ? ApiException::authorizationPending()
                : ApiException::slowDown(DeviceAuthorization::INTERVAL_SECONDS + DeviceAuthorization::SLOW_DOWN_SECONDS);
        }

        throw_if($authorization->status === DeviceAuthorizationStatus::Denied, ApiException::accessDenied());

        $device = Device::query()
            ->with('user')
            ->where('fingerprint', $authorization->fingerprint)
            ->where('user_id', $authorization->userId)
            ->first();

        throw_unless($device instanceof Device, ApiException::invalidGrant());

        return $device;
    }
}
