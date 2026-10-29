<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Profile;
use Illuminate\Http\RedirectResponse;

final readonly class OrganizationMentionController
{
    /**
     * Mentions link to the organization they captured, so they still lead to its page after it's renamed.
     */
    public function show(Profile $organization): RedirectResponse
    {
        return to_route('user.show', ['user' => $organization->handle]);
    }
}
