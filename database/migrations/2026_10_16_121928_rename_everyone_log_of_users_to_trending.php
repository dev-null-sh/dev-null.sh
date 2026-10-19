<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * The Everyone log became the Trending log, so whoever last visited it keeps landing on it.
     */
    public function up(): void
    {
        DB::table('users')->where('log', 'everyone')->update(['log' => 'trending']);
    }
};
