<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('notifications')
            ->where('type', 'replied')
            ->whereNull('subject_id')
            ->update(['subject_id' => DB::raw('(select parent_id from commits where commits.id = notifications.commit_id)')]);
    }
};
