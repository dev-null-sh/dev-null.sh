<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Mentions and stale usernames look people up by their lowercase username, and deleting a commit unpins it.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->rawIndex('lower(username)', 'users_lower_username_index');
            $table->index('pinned_commit_id');
        });
    }
};
