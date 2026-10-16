<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Counting unread notifications and marking them as read only look at the few unread notifications of a recipient,
     * which this index finds without going through everything they were ever notified about.
     */
    public function up(): void
    {
        $unread = in_array(DB::getDriverName(), ['mysql', 'mariadb'], true) ? '' : 'where read_at is null';

        DB::statement("create index notifications_unread_recipient_id_created_at_index on notifications (recipient_id, created_at) {$unread}");
    }
};
