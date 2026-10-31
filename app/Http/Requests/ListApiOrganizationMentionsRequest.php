<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Organization;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;

final class ListApiOrganizationMentionsRequest extends FormRequest
{
    private ?Organization $organization = null;

    public function authorize(): Response
    {
        return $this->organization() instanceof Organization ? Response::allow() : Response::denyAsNotFound();
    }

    /**
     * @return array{}
     */
    public function rules(): array
    {
        return [];
    }

    /**
     * Only organizations that already have a page, so looking one up never asks GitHub.
     */
    public function organization(): ?Organization
    {
        $handle = $this->route('handle');
        assert(is_string($handle));

        return $this->organization ??= Organization::query()
            ->whereRelation('profile', 'handle', $handle)
            ->first();
    }
}
