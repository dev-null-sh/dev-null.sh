<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The organization a scheduled commit goes out as, or null for the user who scheduled it, which only they see in
     * their queue. Deleting the organization deletes its scheduled commits first, in code, so it never cascades.
     */
    public function up(): void
    {
        Schema::table('scheduled_commits', function (Blueprint $table): void {
            $table->foreignUuid('author_id')->nullable()->index()->constrained('profiles');
        });
    }
};
