<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Organization;

final readonly class UpdateOrganizationEmbeds
{
    public function handle(Organization $organization, bool $allowed): void
    {
        $organization->update(['allows_embeds' => $allowed]);
    }
}
