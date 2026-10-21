<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\OrgRole;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;

final class UpdateOrgVisibilityRequest extends FormRequest
{
    /**
     * Only sponsors hide, in a sponsors org: everyone sees its maintainer.
     */
    public function authorize(): Response
    {
        $user = $this->user();
        assert($user instanceof User);

        $org = $this->route('org');
        assert($org instanceof Conversation);

        return match (true) {
            ! $user->remotes_enabled || ! $org->roleOf($user) instanceof OrgRole => Response::denyAsNotFound(),
            ! $org->isSponsors() || $org->isMaintainedBy($user) => Response::deny('Only sponsors can hide their membership.'),
            default => Response::allow(),
        };
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'is_hidden' => ['required', 'boolean'],
        ];
    }
}
