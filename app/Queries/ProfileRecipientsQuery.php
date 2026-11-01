<?php

declare(strict_types=1);

namespace App\Queries;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

final readonly class ProfileRecipientsQuery
{
    /**
     * Who hears about what happens to each profile, to join on `profile_id`: a user's is the user, and an
     * organization's is each of its members, with the organization they hear about it for.
     */
    public function builder(): Builder
    {
        return DB::table('users')->select(['id as profile_id', 'id as user_id', DB::raw('cast(null as uuid) as organization_id')])
            ->unionAll(DB::table('organization_members')->select(['organization_id as profile_id', 'user_id', 'organization_id']));
    }
}
