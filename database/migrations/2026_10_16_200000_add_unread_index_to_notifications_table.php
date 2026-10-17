<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Every page counts the unread notifications, starting from the oldest one, which is quick to find among a few unread
     * rows but slow among a long read history.
     */
    public function up(): void
    {
        $unread = in_array(DB::getDriverName(), ['mysql', 'mariadb'], true) ? '' : 'where read_at is null';

        DB::statement("create index notifications_unread_recipient_id_created_at_index on notifications (recipient_id, created_at) {$unread}");
    }
};
