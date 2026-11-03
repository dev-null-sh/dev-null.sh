<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Each statement takes its own lock, as briefly as it can, and the index is built without locking the table.
     */
    public $withinTransaction = false;

    /**
     * The organization a notification is for, when its recipient gets it as one of the organization's members:
     * about the organization's commits, or a mention of it. Null when it is the recipient's own. Deleting the
     * organization deletes those notifications first, in code.
     *
     * The foreign key is checked apart from its creation, so the scan doesn't hold up new notifications, and the
     * index only has the organizations' notifications. Safe to run again.
     */
    public function up(): void
    {
        DB::statement('alter table notifications add column if not exists organization_id uuid');
        DB::statement('alter table notifications drop constraint if exists notifications_organization_id_foreign');
        DB::statement('alter table notifications add constraint notifications_organization_id_foreign foreign key (organization_id) references organizations (id) not valid');
        DB::statement('alter table notifications validate constraint notifications_organization_id_foreign');
        DB::statement('drop index concurrently if exists notifications_organization_id_index');
        DB::statement('create index concurrently notifications_organization_id_index on notifications (organization_id) where organization_id is not null');
    }
};
