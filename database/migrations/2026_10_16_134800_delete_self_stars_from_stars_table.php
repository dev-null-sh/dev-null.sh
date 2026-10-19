<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('stars')
            ->whereExists(fn (Builder $query): Builder => $query
                ->select(DB::raw(1))
                ->from('commits')
                ->whereColumn('commits.id', 'stars.commit_id')
                ->whereColumn('commits.user_id', 'stars.user_id'))
            ->delete();
    }
};
