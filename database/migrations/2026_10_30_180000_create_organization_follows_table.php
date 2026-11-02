<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The GitHub organizations users follow, listed with the people they follow and counted with them. Members see
     * them, like follows, and everyone sees how many. A user's rows go when they erase their account, and an
     * organization's when GitHub no longer has it.
     */
    public function up(): void
    {
        Schema::create('organization_follows', function (Blueprint $table): void {
            $table->foreignUuid('user_id')->constrained();
            $table->foreignUuid('organization_id')->constrained();
            $table->timestamp('created_at');

            $table->primary(['user_id', 'organization_id']);
            $table->index('organization_id');
        });
    }
};
