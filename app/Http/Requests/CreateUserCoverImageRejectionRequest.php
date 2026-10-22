<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;

final class CreateUserCoverImageRejectionRequest extends FormRequest
{
    /**
     * Moderators can take down approved covers too. One replaced since the moderator saw it is left alone.
     */
    public function authorize(): Response
    {
        $moderator = $this->user();
        assert($moderator instanceof User);

        if (! $moderator->isAdmin()) {
            return Response::deny();
        }

        $user = $this->route('user');
        assert($user instanceof User);

        return $user->coverImageVersion() === $this->string('version')->value()
            ? Response::allow()
            : Response::denyAsNotFound();
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'version' => ['required', 'string'],
        ];
    }
}
