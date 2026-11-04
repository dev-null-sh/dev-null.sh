<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Each statement takes its own lock, as briefly as it can.
     */
    public $withinTransaction = false;

    /**
     * Whoever did what a notification is about is a profile: a user, or an organization replying, quoting or
     * mentioning. A user's profile has the user's id, so every actor already is one. Deleting the profile still
     * deletes what it did, as deleting the user did.
     *
     * The new foreign key replaces the old one in one transaction, so no actor goes unchecked in between, and is
     * checked apart from its creation, so the scan doesn't hold up new notifications. Safe to run again.
     */
    public function up(): void
    {
        DB::transaction(function (): void {
            DB::statement('alter table notifications drop constraint if exists notifications_actor_id_foreign');
            DB::statement('alter table notifications add constraint notifications_actor_id_foreign foreign key (actor_id) references profiles (id) on delete cascade not valid');
        });

        DB::statement('alter table notifications validate constraint notifications_actor_id_foreign');
    }
};
