<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Device;
use Illuminate\Foundation\Http\FormRequest;

final class DeleteApiDevicePushTokenRequest extends FormRequest
{
    /**
     * Only ever the device that signed the request, so there is no other one to name.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array{}
     */
    public function rules(): array
    {
        return [];
    }

    public function device(): Device
    {
        $device = $this->attributes->get(Device::class);
        assert($device instanceof Device);

        return $device;
    }
}
