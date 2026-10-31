<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A user shares their profile's ID, as an organization does, so a profile's ID names a person or an organization
     * alike, and anything a person or an organization can do can point to profiles. Nothing points to the profiles of
     * users yet, so they take the ID of their user, which everything already points to.
     */
    public function up(): void
    {
        // The migration runs in one transaction. Users and profiles can still be read but wait to be written until it
        // commits, so no user signs up with another ID between the update and the new foreign key.
        DB::statement('lock table profiles, users in share row exclusive mode');

        DB::statement('update profiles set id = users.id from users where users.github_id = profiles.github_id and profiles.id <> users.id');

        Schema::table('users', function (Blueprint $table): void {
            $table->foreign('id')->references('id')->on('profiles');
        });
    }
};
