<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\PushPreference;
use Illuminate\Foundation\Http\FormRequest;

final class UpdateApiUserPushPreferencesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Every switch, each on or off.
     *
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return collect(PushPreference::cases())->mapWithKeys(fn (PushPreference $preference): array => [
            $preference->value => ['required', 'boolean'],
        ])->all();
    }

    /**
     * @return array<value-of<PushPreference>, bool>
     */
    public function preferences(): array
    {
        return collect(PushPreference::cases())->mapWithKeys(fn (PushPreference $preference): array => [
            $preference->value => $this->boolean($preference->value),
        ])->all();
    }
}
