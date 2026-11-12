<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Exceptions\ApiException;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

final class ListApiUserFollowingRequest extends FormRequest
{
    /**
     * Anyone may list the people a user follows, but the web shows neither of two people across a block the other's
     * connections, so a signed request across one gets a code instead.
     */
    public function authorize(): bool
    {
        $user = $this->route('user');
        assert($user instanceof User);

        $viewer = $this->user();
        assert($viewer instanceof User);

        $blockerId = $viewer->blockerIdWith($user);

        throw_if($blockerId !== null, fn (): ApiException => ApiException::acrossBlock($user, blockedByViewer: $blockerId === $viewer->id, shown: UserResource::make($user)));

        return true;
    }

    /**
     * @return array{}
     */
    public function rules(): array
    {
        return [];
    }
}
