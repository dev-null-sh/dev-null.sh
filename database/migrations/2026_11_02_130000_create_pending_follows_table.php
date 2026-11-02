<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The members waiting to follow an unclaimed developer: the ones following them on GitHub when they were looked up,
     * and the ones who followed their page. Everyone sees how many, and members the ones they follow among them. They
     * become follows, and go, once the developer signs in; a member's go when they erase their account or stop waiting,
     * and a developer's when their unclaimed profile goes.
     */
    public function up(): void
    {
        Schema::create('pending_follows', function (Blueprint $table): void {
            $table->foreignUuid('user_id')->constrained();
            $table->foreignUuid('profile_id')->constrained();

            $table->primary(['user_id', 'profile_id']);
            $table->index('profile_id');
        });
    }
};
