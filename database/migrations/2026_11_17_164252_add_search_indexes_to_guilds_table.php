<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::ensureExtensionExists('pg_trgm');

        Schema::table('guilds', function (Blueprint $table): void {
            $table->rawIndex('translate(lower(handle COLLATE "pg_c_utf8"), \'ς\', \'σ\') gin_trgm_ops', 'guilds_lower_handle_trigram_index')->algorithm('gin');
            $table->rawIndex('translate(lower(name COLLATE "pg_c_utf8"), \'ς\', \'σ\') gin_trgm_ops', 'guilds_lower_name_trigram_index')->algorithm('gin');
            $table->rawIndex('translate(lower(description COLLATE "pg_c_utf8"), \'ς\', \'σ\') gin_trgm_ops', 'guilds_lower_description_trigram_index')->algorithm('gin');
        });
    }
};
