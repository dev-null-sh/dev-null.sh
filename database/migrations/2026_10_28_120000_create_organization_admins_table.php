<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The users who claimed an organization's page, which GitHub confirmed they belong to. Everyone sees them on the
     * page. A user's rows go when they erase their account, and an organization's when GitHub no longer has it.
     */
    public function up(): void
    {
        Schema::create('organization_admins', function (Blueprint $table): void {
            $table->foreignUuid('organization_id')->constrained('profiles');
            $table->foreignUuid('user_id')->constrained();
            $table->timestamp('created_at');

            $table->primary(['organization_id', 'user_id']);
            $table->index('user_id');
        });
    }
};
