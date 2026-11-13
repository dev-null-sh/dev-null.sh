<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Updating a commit rewrites its entries in every index, the embeddings' too, so the commits are filled a thousand
     * at a time, each batch on its own, rather than in one transaction that would lock the table for as long.
     */
    public $withinTransaction = false;

    /**
     * So far everyone posts as themselves, and users share the ID of their profile, so a commit is from its user.
     */
    public function up(): void
    {
        while (DB::update('update commits set author_id = user_id where id in (select id from commits where author_id is null and user_id is not null limit 1000)') > 0) {
            //
        }
    }
};
