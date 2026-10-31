<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * When the organizations a user follows on GitHub were imported, apart from the people, since the users who signed
     * up before organizations could be followed import them later.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->timestamp('github_organization_follows_imported_at')->nullable();
        });
    }
};
