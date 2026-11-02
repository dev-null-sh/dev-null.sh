<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\CreateOrganizationOpenGraphImage;
use App\Actions\CreateUserOpenGraphImage;
use App\Models\Organization;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Http\Response;

final readonly class ProfileOpenGraphImageController
{
    /**
     * Users and organizations share the "@handle" paths, so the profile at the handle says which card to draw. A
     * profile that is neither a user's nor an organization's has none.
     */
    public function show(Profile $profile, CreateUserOpenGraphImage $users, CreateOrganizationOpenGraphImage $organizations): Response
    {
        return response($profile->user instanceof User ? $users->handle($profile->user) : $organizations->handle(Organization::query()->findOrFail($profile->id)), headers: [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }
}
