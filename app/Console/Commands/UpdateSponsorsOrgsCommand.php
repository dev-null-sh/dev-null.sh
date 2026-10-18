<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\UpdateSponsorsOrgMembers;
use App\Enums\SponsorsFrom;
use App\Models\Conversation;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('sponsors:update-orgs')]
#[Description('Let one-time sponsors out of the sponsors orgs that only take active sponsors, once their 30 days are up')]
final class UpdateSponsorsOrgsCommand extends Command
{
    public function handle(UpdateSponsorsOrgMembers $action): int
    {
        Conversation::query()
            ->whereIn('sponsors_from', [SponsorsFrom::Monthly, SponsorsFrom::Active])
            ->lazyById(100)
            ->each(fn (Conversation $org) => $action->handle($org));

        return self::SUCCESS;
    }
}
