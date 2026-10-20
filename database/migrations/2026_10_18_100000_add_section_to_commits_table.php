<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Commits posted to a section are chosen when composing and only ever listed on the section's own page, so every other
     * list leaves them out. With `section` in the index of the user's commits as well, the counts of a profile still read
     * the index alone.
     */
    public function up(): void
    {
        $include = in_array(DB::getDriverName(), ['mysql', 'mariadb'], true) ? '' : 'include (status, deleted_at, section)';

        Schema::table('commits', function (Blueprint $table): void {
            $table->string('section')->nullable();
            $table->index(['section', 'created_at']);
            $table->dropIndex(['user_id', 'created_at']);
        });

        DB::statement("create index commits_user_id_created_at_index on commits (user_id, created_at) {$include}");
    }
};
