<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The first member to claim an organization's page through GitHub is its admin, and admins add the rest of its
     * members on /dev/null, each an admin or a member. Only the organization's members see who they are, and admins
     * are listed on the page. Everyone who claimed a page so far stays its admin. A user's rows go when they erase
     * their account, and an organization's when GitHub no longer has it.
     */
    public function up(): void
    {
        Schema::create('organization_members', function (Blueprint $table): void {
            $table->foreignUuid('organization_id')->constrained('profiles');
            $table->foreignUuid('user_id')->constrained();
            $table->string('role');
            $table->timestamp('created_at');

            $table->primary(['organization_id', 'user_id']);
            $table->index('user_id');
        });

        DB::table('organization_members')->insertUsing(
            ['organization_id', 'user_id', 'role', 'created_at'],
            DB::table('organization_admins')->select(['organization_id', 'user_id', DB::raw("'admin'"), 'created_at']),
        );

        Schema::drop('organization_admins');
    }
};
