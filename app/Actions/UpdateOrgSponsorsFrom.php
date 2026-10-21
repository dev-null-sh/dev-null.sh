<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\SponsorsFrom;
use App\Models\Conversation;

final readonly class UpdateOrgSponsorsFrom
{
    public function __construct(private UpdateSponsorsOrgMembers $updateSponsorsOrgMembers)
    {
        //
    }

    public function handle(Conversation $org, SponsorsFrom $from): void
    {
        $org->update(['sponsors_from' => $from]);

        $this->updateSponsorsOrgMembers->handle($org);
    }
}
