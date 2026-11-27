<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The profile a commit is from, which everyone sees: a person's own, or one day an organization's, apart from the
     * user who posted it, which only moderation needs. Deleting the profile leaves its commits without one, as
     * tombstones. It is filled by the next migration, outside of this one's lock on the table.
     */
    public function up(): void
    {
        Schema::table('commits', function (Blueprint $table): void {
            $table->foreignUuid('author_id')->nullable()->constrained('profiles')->nullOnDelete();
        });
    }
};
