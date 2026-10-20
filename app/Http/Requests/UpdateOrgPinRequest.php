<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\OrgRole;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateOrgPinRequest extends FormRequest
{
    public function authorize(): Response
    {
        $user = $this->user();
        assert($user instanceof User);
        $org = $this->route('org');
        assert($org instanceof Conversation);

        return match (true) {
            ! $user->remotes_enabled || ! $org->roleOf($user) instanceof OrgRole => Response::denyAsNotFound(),
            ! $org->isMaintainedBy($user) => Response::deny('Only maintainers can pin messages.'),
            default => Response::allow(),
        };
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        $org = $this->route('org');
        assert($org instanceof Conversation);
        $user = $this->user();
        assert($user instanceof User);
        $visibleFrom = $org->participants()->where('user_id', $user->id)->sole()->visible_from_id;

        return [
            'message_id' => ['present', 'nullable', 'integer', Rule::exists('messages', 'id')->where(fn ($query) => $query
                ->where('conversation_id', $org->id)->whereNull('event')->where('id', '>', $visibleFrom))],
        ];
    }
}
