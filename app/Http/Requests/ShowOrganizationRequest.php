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
            'tab' => ['nullable', 'string', 'in:mentions'],
        ];
    }

    /**
     * Mentions is the only tab so far, so it's the default.
     */
    public function tab(): string
    {
        return 'mentions';
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
