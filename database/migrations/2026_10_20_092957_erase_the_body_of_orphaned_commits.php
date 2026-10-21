<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('commits')
            ->whereNull('user_id')
            ->update(['body' => '', 'mentions' => null, 'embedding' => null, 'link_preview_id' => null]);
    }
};
