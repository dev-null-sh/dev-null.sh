<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Dto\DeviceAuthorization;
use App\Enums\DeviceAuthorizationStatus;
use App\Services\DeviceAuthorizations;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The user approves or denies the device behind the code they typed, from the page that showed them which it is.
 */
final class CreateDeviceActivationRequest extends FormRequest
{
    private ?DeviceAuthorization $authorization = null;

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
            'user_code' => ['required', ...ShowDeviceActivationRequest::userCodeRules()],
            'decision' => ['required', 'string', 'in:approved,denied'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ShowDeviceActivationRequest::userCodeMessages();
    }

    /**
     * Only a sign-in still waiting for a decision can be decided: one decided or expired since the page was shown
     * gets the same answer as a code that never was.
     */
    public function passedValidation(): void
    {
        $authorization = resolve(DeviceAuthorizations::class)->pendingFromUserCode($this->string('user_code')->value());

        throw_unless($authorization instanceof DeviceAuthorization, ShowDeviceActivationRequest::invalid($this, ShowDeviceActivationRequest::unknownCode()));

        $this->authorization = $authorization;
    }

    public function authorization(): DeviceAuthorization
    {
        assert($this->authorization instanceof DeviceAuthorization);

        return $this->authorization;
    }

    public function decision(): DeviceAuthorizationStatus
    {
        $decision = $this->enum('decision', DeviceAuthorizationStatus::class);

        assert($decision instanceof DeviceAuthorizationStatus);

        return $decision;
    }

    public function prepareForValidation(): void
    {
        ShowDeviceActivationRequest::normalizeUserCode($this);
    }

    public function failedValidation(Validator $validator): never
    {
        throw ShowDeviceActivationRequest::invalid($this, $validator->errors()->first());
    }
}
