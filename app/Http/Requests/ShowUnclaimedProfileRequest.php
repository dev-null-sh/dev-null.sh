<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Profile;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;

final class ShowUnclaimedProfileRequest extends FormRequest
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
     * Unclaimed developers share the "@handle" pages with users, so this is the profile at the handle, whatever its case.
     */
    public function profile(): ?Profile
    {
        $handle = $this->route('user');
        assert(is_string($handle));

        return $this->profile ??= Profile::query()->firstWhere('handle', $handle);
    }
}
