<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class CreateOrganizationAdminRequest extends FormRequest
{
    /**
     * Only organizations' pages can be claimed, and the route only finds organizations: a user's profile is a 404.
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
}
