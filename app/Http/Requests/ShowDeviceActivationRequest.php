<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Dto\DeviceAuthorization;
use App\Services\DeviceAuthorizations;
use App\Services\UserCodes;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Inertia\Inertia;

/**
 * The user opens this page to type the code their terminal shows, or lands on it with the code already in the URL,
 * and is then asked to approve the device behind it.
 */
final class ShowDeviceActivationRequest extends FormRequest
{
    private ?DeviceAuthorization $authorization = null;

    /**
     * The code as the terminal shows it, once what the user typed is tidied up.
     *
     * @return list<string>
     */
    public static function userCodeRules(): array
    {
        return ['string', 'regex:/^['.UserCodes::Alphabet.']{4}-['.UserCodes::Alphabet.']{4}$/'];
    }

    /**
     * @return array<string, string>
     */
    public static function userCodeMessages(): array
    {
        return ['user_code.regex' => __('A code is eight letters, like WDJB-MJHT.')];
    }

    public static function unknownCode(): string
    {
        return __('This code is unknown or has expired. Check your terminal, and start the sign-in again if you need to.');
    }

    /**
     * Tidies up the code the user typed, so it is read however they typed it: in lowercase, without the dash, with
     * spaces around.
     */
    public static function normalizeUserCode(FormRequest $request): void
    {
        $typed = $request->input('user_code');

        if (is_string($typed) && $typed !== '') {
            $request->merge(['user_code' => resolve(UserCodes::class)->normalize($typed)]);
        }
    }

    /**
     * A code that can't be found, or a request that isn't right, gets the page again with what is wrong and the code
     * as it was read, for the user to fix it. Nothing is decided, and nothing is redirected.
     */
    public static function invalid(FormRequest $request, string $error): HttpResponseException
    {
        $typed = $request->input('user_code');

        return new HttpResponseException(Inertia::render('device-activation/show', [
            'error' => $error,
            'userCode' => is_string($typed) ? $typed : '',
        ])->toResponse($request)->setStatusCode(400));
    }

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
            'user_code' => ['nullable', ...self::userCodeRules()],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return self::userCodeMessages();
    }

    /**
     * The sign-in behind the code, when the user typed one: it has to be one that is still waiting for a decision.
     */
    public function passedValidation(): void
    {
        if (! $this->filled('user_code')) {
            return;
        }

        $authorization = resolve(DeviceAuthorizations::class)->pendingFromUserCode($this->string('user_code')->value());

        throw_unless($authorization instanceof DeviceAuthorization, self::invalid($this, self::unknownCode()));

        $this->authorization = $authorization;
    }

    public function authorization(): ?DeviceAuthorization
    {
        return $this->authorization;
    }

    public function prepareForValidation(): void
    {
        self::normalizeUserCode($this);
    }

    public function failedValidation(Validator $validator): never
    {
        throw self::invalid($this, $validator->errors()->first());
    }
}
