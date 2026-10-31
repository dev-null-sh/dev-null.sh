<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Device;
use App\Models\User;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;

final class DeleteApiDeviceRequest extends FormRequest
{
    /**
     * Another user's device looks like it does not exist.
     */
    public function authorize(): Response
    {
        $user = $this->user();
        assert($user instanceof User);

        $device = $this->route('device');
        assert($device instanceof Device);

        return $device->user_id === $user->id ? Response::allow() : Response::denyAsNotFound();
    }

    /**
     * @return array{}
     */
    public function rules(): array
    {
        return [];
    }
}
