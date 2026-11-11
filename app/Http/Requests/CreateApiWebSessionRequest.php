<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Dto\MobileAuthorization;
use App\Models\Device;
use Illuminate\Foundation\Http\FormRequest;

final class CreateApiWebSessionRequest extends FormRequest
{
    /**
     * Only the lite mobile app has a web view to sign in, so a key approved for another app stays limited to the API.
     * An app says its own id when the user approves it, so this keeps other apps out by default rather than proving
     * which app is asking: the approval is what authorizes the key.
     */
    public function authorize(): bool
    {
        return $this->device()->app_id === MobileAuthorization::LiteMobileAppId;
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
