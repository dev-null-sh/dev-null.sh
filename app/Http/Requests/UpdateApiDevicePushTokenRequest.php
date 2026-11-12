<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\PushPlatform;
use App\Models\Device;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

final class UpdateApiDevicePushTokenRequest extends FormRequest
{
    /**
     * Only ever the device that signed the request, so there is no other one to name.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<string|Enum>>
     */
    public function rules(): array
    {
        return [
            'token' => ['required', 'string', 'max:4096'],
            'platform' => ['required', Rule::enum(PushPlatform::class)],
        ];
    }

    public function device(): Device
    {
        $device = $this->attributes->get(Device::class);
        assert($device instanceof Device);

        return $device;
    }

    public function platform(): PushPlatform
    {
        $platform = $this->enum('platform', PushPlatform::class);
        assert($platform instanceof PushPlatform);

        return $platform;
    }
}
