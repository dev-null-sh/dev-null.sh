<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Streams that ran out are cleared, and so is every stream shared by more than one user, as there is no telling whose it is.
     */
    public function up(): void
    {
        $cleared = ['live_platform' => null, 'live_url' => null, 'live_ends_at' => null];

        DB::table('users')->where('live_ends_at', '<=', now())->update($cleared);

        DB::table('users')
            ->whereIn('live_url', DB::table('users')->select('live_url')->whereNotNull('live_url')->groupBy('live_url')->havingRaw('COUNT(*) > 1'))
            ->update($cleared);

        Schema::table('users', function (Blueprint $table): void {
            $table->unique('live_url');
        });
    }
};
