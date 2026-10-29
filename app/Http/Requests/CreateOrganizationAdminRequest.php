<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\ProfileType;
use App\Models\Profile;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;

final class CreateOrganizationAdminRequest extends FormRequest
{
    /**
     * Only organizations' pages can be claimed. A user's profile looks like it does not exist.
     */
    public function authorize(): Response
    {
        $organization = $this->route('organization');
        assert($organization instanceof Profile);

        return $organization->type === ProfileType::Organization ? Response::allow() : Response::denyAsNotFound();
    }

    /**
     * @return array{}
     */
    public function rules(): array
    {
        return [];
    }
}
