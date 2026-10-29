<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\OrgRole;
use App\Enums\PingsFrom;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

final class UpdateOrgPingsFromRequest extends FormRequest
{
    public function authorize(): Response
    {
        $user = $this->user();
        assert($user instanceof User);

        $org = $this->route('org');
        assert($org instanceof Conversation);

        return match (true) {
            ! $user->remotes_enabled || ! $org->roleOf($user) instanceof OrgRole => Response::denyAsNotFound(),
            ! $org->isMaintainedBy($user) => Response::deny('Only maintainers can choose who can ping the org.'),
            default => Response::allow(),
        };
    }

    /**
     * @return array<string, list<string|Enum>>
     */
    public function rules(): array
    {
        return [
            'pings_from' => ['required', Rule::enum(PingsFrom::class)],
        ];
    }

    public function pingsFrom(): PingsFrom
    {
        return $this->enum('pings_from', PingsFrom::class) ?? PingsFrom::Everyone;
    }
}
