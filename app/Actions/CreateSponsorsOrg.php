<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\SponsorsFrom;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final readonly class CreateSponsorsOrg
{
    public function __construct(private CreateOrg $createOrg)
    {
        //
    }

    /**
     * Starts out with just the maintainer: their sponsors are let in once GitHub has been asked who they are.
     */
    public function handle(User $maintainer, SponsorsFrom $from): Conversation
    {
        return DB::transaction(function () use ($maintainer, $from): Conversation {
            $org = $this->createOrg->handle($maintainer, "@{$maintainer->username}'s sponsors", new Collection());

            $org->update(['sponsors_from' => $from]);

            return $org;
        });
    }
}
