<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\CreateProfileFeed;
use App\Models\Profile;
use Illuminate\Http\Response;

final readonly class ProfileFeedController
{
    public function show(Profile $profile, CreateProfileFeed $action): Response
    {
        return response($action->handle($profile), headers: [
            'Content-Type' => 'application/atom+xml; charset=UTF-8',
        ]);
    }
}
