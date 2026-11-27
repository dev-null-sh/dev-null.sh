<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The accounts a user waits to follow until they are on /dev/null, by GitHub ID: the organizations they follow on
     * GitHub, and now the developers they follow there or from their unclaimed page. The developer's page shows how
     * many wait, and members the ones they follow among them; the user sees theirs in their export. A row goes when its
     * account shows up and the wait becomes a follow, when the user stops waiting, or with the user.
     *
     * Its keys take the new name too, so the schema builder finds them by the table's name.
     */
    public function up(): void
    {
        Schema::rename('pending_organization_follows', 'pending_follows');

        DB::statement('alter index pending_organization_follows_pkey rename to pending_follows_pkey');
        DB::statement('alter index pending_organization_follows_github_id_index rename to pending_follows_github_id_index');
        DB::statement('alter table pending_follows rename constraint pending_organization_follows_user_id_foreign to pending_follows_user_id_foreign');
    }
};
