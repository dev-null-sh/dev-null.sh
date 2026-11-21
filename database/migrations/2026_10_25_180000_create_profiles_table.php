<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A profile mirrors a GitHub account at its "@handle": for now the GitHub identity of users, moved off the users
     * table. Users find theirs by the GitHub ID they keep, which never changes. The old columns stay, nullable and
     * unused, until a later migration drops them, so the code still running during a deploy keeps working.
     */
    public function up(): void
    {
        Schema::ensureExtensionExists('pg_trgm');

        Schema::create('profiles', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->string('github_id')->unique();
            $table->string('handle')->unique();
            $table->string('name');
            $table->string('avatar_url')->nullable();
            $table->text('bio')->nullable();
            $table->string('location')->nullable();
            $table->string('website')->nullable();
            $table->timestamp('github_synced_at')->nullable();
            $table->timestamps();

            // Mentions and stale handles look profiles up by their lowercase handle, people search and mention
            // suggestions match any part of the lowercase handle or name.
            $table->rawIndex('lower(handle)', 'profiles_lower_handle_index');
            $table->rawIndex('lower(handle) gin_trgm_ops', 'profiles_lower_handle_trigram_index')->algorithm('gin');
            $table->rawIndex('lower(name) gin_trgm_ops', 'profiles_lower_name_trigram_index')->algorithm('gin');
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->string('username')->nullable()->change();
            $table->string('name')->nullable()->change();
        });
    }
};
