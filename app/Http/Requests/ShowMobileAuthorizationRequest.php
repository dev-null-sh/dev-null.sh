<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Dto\MobileAuthorization;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Inertia\Inertia;

/**
 * The devnull app opens this page in a browser to be approved, with a PKCE challenge, the way an OAuth app would.
 */
final class ShowMobileAuthorizationRequest extends FormRequest
{
    /**
     * The rules for what the app sends, on the page and again when the user approves it.
     *
     * The code only ever goes back to the app: its own devnull:// scheme, or a port on the loopback address for the
     * command line, which can't rely on macOS sending the scheme to it instead of the Mac app (RFC 8252, section 7.3).
     *
     * @return array<string, list<string>>
     */
    public static function parameterRules(): array
    {
        return [
            'fingerprint' => ['required', 'string', 'regex:/^SHA256:[A-Za-z0-9+\/]{43}$/'],
            'device_name' => ['required', 'string', 'max:100'],
            'code_challenge' => ['required', 'string', 'regex:/^[A-Za-z0-9_-]{43}$/'],
            'code_challenge_method' => ['required', 'string', 'in:S256'],
            'state' => ['required', 'string', 'max:255'],
            'redirect_uri' => ['required', 'string', 'max:255', 'regex:/^(devnull:\/\/|http:\/\/127\.0\.0\.1:[0-9]{1,5}([\/?]|$))/', 'not_regex:/#/'],
        ];
    }

    /**
     * A request the app didn't make right gets a plain error page, and never a redirect, so nothing is sent anywhere.
     */
    public static function invalid(FormRequest $request, Validator $validator): HttpResponseException
    {
        return new HttpResponseException(Inertia::render('mobile-authorization/show', [
            'error' => $validator->errors()->first(),
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
        return self::parameterRules();
    }

    /**
     * Apps that leave the fingerprint's "+" unencoded in the query, like the macOS app, have it read back as a space.
     */
    public function prepareForValidation(): void
    {
        $this->merge(['fingerprint' => str_replace(' ', '+', $this->string('fingerprint')->value())]);
    }

    public function authorization(): MobileAuthorization
    {
        return new MobileAuthorization(
            $this->string('fingerprint')->value(),
            $this->string('device_name')->value(),
            $this->string('code_challenge')->value(),
            $this->string('state')->value(),
            $this->string('redirect_uri')->value(),
        );
    }

    public function failedValidation(Validator $validator): never
    {
        throw self::invalid($this, $validator);
    }
}
