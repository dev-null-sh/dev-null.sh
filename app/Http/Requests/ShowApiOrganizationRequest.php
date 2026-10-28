<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\ProfileType;
use App\Models\Profile;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;

final class ShowApiOrganizationRequest extends FormRequest
{
    private ?Profile $organization = null;

    public function authorize(): Response
    {
        return $this->organization() instanceof Profile ? Response::allow() : Response::denyAsNotFound();
    }

    /**
     * @return array{}
     */
    public function rules(): array
    {
        return [];
    }

    /**
     * The organization at the handle in any case, as a mention keeps the case it was written in.
     */
    public function organization(): ?Profile
    {
        $handle = $this->route('organization');
        assert(is_string($handle));

        return $this->organization ??= Profile::query()
            ->where('type', ProfileType::Organization)
            ->whereRaw('LOWER(handle) = ?', [mb_strtolower($handle)])
            ->first();
    }
}
