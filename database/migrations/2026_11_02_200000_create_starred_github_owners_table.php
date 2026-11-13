<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The people whose repositories a user starred on GitHub, by GitHub ID, with how many of them, so Who to follow
     * can suggest the ones on /dev/null, now or once they join. Only suggestions read them, and nobody sees them but
     * the user in their export. A row goes with its user.
     */
    public function up(): void
    {
        Schema::create('starred_github_owners', function (Blueprint $table): void {
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->string('github_id');
            $table->unsignedSmallInteger('repositories_count');
            $table->timestamp('created_at');

            $table->primary(['user_id', 'github_id']);
            $table->index('github_id');
        });
    }
};
