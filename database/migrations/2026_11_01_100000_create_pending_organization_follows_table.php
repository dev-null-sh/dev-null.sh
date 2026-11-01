<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The organizations a user follows on GitHub that aren't on /dev/null yet, by GitHub ID, so the user follows them
     * here once they are. Only the follow import reads them, and nobody sees them but the user in their export. A row
     * goes when its organization shows up, or with its user.
     */
    public function up(): void
    {
        Schema::create('pending_organization_follows', function (Blueprint $table): void {
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->string('github_id');
            $table->timestamp('created_at');

            $table->primary(['user_id', 'github_id']);
            $table->index('github_id');
        });
    }
};
