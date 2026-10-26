<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * People search also matches any part of the lowercase bio, so it needs the same trigram index as username and name.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->rawIndex('lower(bio) gin_trgm_ops', 'users_lower_bio_trigram_index')->algorithm('gin');
        });
    }
};
