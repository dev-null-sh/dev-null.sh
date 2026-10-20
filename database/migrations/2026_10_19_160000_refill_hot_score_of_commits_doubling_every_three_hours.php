<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Scores now double every three hours instead of six, so new commits take over trending sooner. Every stored score is
     * on another scale than the new ones and must be moved, or any commit rescored after the deploy would rank above
     * every commit that was not. Only the time term changes, from created_at / 6h to created_at / 3h, so adding
     * created_at / 6h to each score moves it without recounting engagement. Running it twice would move it twice.
     */
    public function up(): void
    {
        DB::update('update commits set hot_score = round((hot_score + extract(epoch from created_at) / 21600)::numeric, 6) where parent_id is null');
    }
};
