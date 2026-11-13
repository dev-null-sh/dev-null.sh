<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Profile;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;

final class DeletePendingFollowRequest extends FormRequest
{
    /**
     * Only a developer who isn't on /dev/null yet is waited for: once they are, members follow them.
     */
    public function authorize(): Response
    {
        $profile = $this->route('profile');
        assert($profile instanceof Profile);

        return $profile->isUnclaimed() ? Response::allow() : Response::denyAsNotFound();
    }

    /**
     * @return array{}
     */
    public function rules(): array
    {
        return [];
    }
}
