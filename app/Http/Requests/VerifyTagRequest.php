<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Conversation;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;

final class VerifyTagRequest extends FormRequest
{
    public function authorize(): Response
    {
        $user = $this->user();
        assert($user instanceof User);
        $tag = $this->tag();

        if (! $tag->isMaintainedBy($user) && ! $user->isAdmin()) {
            return Response::deny('Only maintainers can verify this hashtag.');
        }

        return Conversation::userMayVerifyBranch($user, $tag->name)
            ? Response::allow()
            : Response::deny('This branch matches a Remotes org; only that org\'s maintainers can verify it.');
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
}
