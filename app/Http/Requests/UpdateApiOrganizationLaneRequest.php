<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;

final class UpdateApiOrganizationLaneRequest extends FormRequest
{
    private ?Organization $organization = null;

    /**
     * Only the organization's admins choose its color, so to everyone else it looks like it does not exist.
     */
    public function authorize(): Response
    {
        $user = $this->user();
        assert($user instanceof User);

        return $this->organization()?->isAdmin($user) === true ? Response::allow() : Response::denyAsNotFound();
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'lane' => ['present', 'nullable', 'integer', 'between:0,5'],
            'color' => ['nullable', 'string', 'regex:/^#[0-9a-f]{6}$/', 'prohibits:lane'],
        ];
    }

    public function organization(): ?Organization
    {
        $handle = $this->route('handle');
        assert(is_string($handle));

        return $this->organization ??= Organization::query()
            ->whereRelation('profile', 'handle', $handle)
            ->first();
    }

    public function lane(): ?int
    {
        return $this->filled('lane') ? $this->integer('lane') : null;
    }

    public function color(): ?string
    {
        return $this->filled('color') ? $this->string('color')->toString() : null;
    }
}
