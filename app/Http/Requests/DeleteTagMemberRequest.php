<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Tag;
use App\Models\User;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;

final class DeleteTagMemberRequest extends FormRequest
{
    public function authorize(): Response
    {
        $user = $this->user();
        assert($user instanceof User);
        $member = $this->member();
        $tag = $this->tag();

        if ($user->id === $member->id) {
            return Response::allow();
        }

        return $tag->isMaintainedBy($user) || $user->isAdmin()
            ? Response::allow()
            : Response::deny('Only maintainers can remove other people.');
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [];
    }

    public function tag(): Tag
    {
        $tag = $this->route('tag');
        assert($tag instanceof Tag);

        return $tag;
    }

    public function member(): User
    {
        $member = $this->route('member');
        assert($member instanceof User);

        return $member;
    }
}
