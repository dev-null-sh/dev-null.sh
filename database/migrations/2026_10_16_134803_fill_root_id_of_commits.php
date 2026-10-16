<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Walks each reply up to the top-level commit of its thread in PHP, as MySQL cannot update a table from a subquery
     * on itself. Only replies without a root are filled, so it picks up where it left off if it is run again.
     */
    public function up(): void
    {
        $parentIds = [];

        foreach (DB::table('commits')->whereNotNull('parent_id')->select(['id', 'parent_id'])->cursor() as $reply) {
            if (is_string($reply->id) && is_string($reply->parent_id)) {
                $parentIds[$reply->id] = $reply->parent_id;
            }
        }

        $replyIdsByRootId = [];

        foreach ($parentIds as $id => $rootId) {
            while (isset($parentIds[$rootId])) {
                $rootId = $parentIds[$rootId];
            }

            $replyIdsByRootId[$rootId][] = $id;
        }

        foreach ($replyIdsByRootId as $rootId => $replyIds) {
            foreach (array_chunk($replyIds, 500) as $chunk) {
                DB::table('commits')->whereIn('id', $chunk)->whereNull('root_id')->update(['root_id' => $rootId]);
            }
        }
    }
};
