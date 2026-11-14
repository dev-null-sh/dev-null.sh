<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Organization;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;

final class ShowOrganizationRequest extends FormRequest
{
    private ?Organization $organization = null;

    public function authorize(): Response
    {
        return $this->organization() instanceof Organization ? Response::allow() : Response::denyAsNotFound();
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'tab' => ['nullable', 'string', 'in:commits,mentions,people'],
        ];
    }

    /**
     * The organization's own commits first, then the commits that mention it, then its people when its admins show them.
     */
    public function tab(): string
    {
        return match ($this->string('tab')->value()) {
            'mentions' => 'mentions',
            'people' => $this->organization()?->shows_people === true ? 'people' : 'commits',
            default => 'commits',
        };
    }

    /**
     * Organizations share the "@handle" pages with users, so this is the handle no user has, whatever its case.
     */
    public function organization(): ?Organization
    {
        $handle = $this->route('user');
        assert(is_string($handle));

        return $this->organization ??= Organization::query()->whereRelation('profile', 'handle', $handle)->first();
    }
}
