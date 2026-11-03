<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\CreateProfileOpenGraphImage;
use App\Actions\CreateUserOpenGraphImage;
use App\Models\Organization;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Http\Response;

final readonly class ProfileOpenGraphImageController
{
    /**
     * Users, organizations and unclaimed developers share the "@handle" paths, so the profile at the handle says which
     * card to draw.
     */
    public function show(Profile $profile, CreateUserOpenGraphImage $users, CreateProfileOpenGraphImage $profiles): Response
    {
        return response(match (true) {
            $profile->user instanceof User => $users->handle($profile->user),
            $profile->isUnclaimed() => $profiles->handle($profile),
            default => $profiles->handle(Organization::query()->findOrFail($profile->id)),
        }, headers: [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }
}
