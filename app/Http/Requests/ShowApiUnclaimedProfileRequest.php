<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Profile;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;

final class ShowApiUnclaimedProfileRequest extends FormRequest
{
    private ?Profile $profile = null;

    public function authorize(): Response
    {
        return $this->profile()?->isUnclaimed() === true ? Response::allow() : Response::denyAsNotFound();
    }

    /**
     * @return array{}
     */
    public function rules(): array
    {
        return [];
    }

    /**
     * Only developers who already have a page, whatever the case of the handle, so looking one up never asks GitHub.
     */
    public function profile(): ?Profile
    {
        $handle = $this->route('handle');
        assert(is_string($handle));

        return $this->profile ??= Profile::query()->firstWhere('handle', $handle);
    }
}
