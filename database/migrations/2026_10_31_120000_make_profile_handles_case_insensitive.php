<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * GitHub handles are unique whatever their case, so profiles' are too: comparing them ignores case, so looking one
     * up needs no lowercasing, and the unique index, rebuilt on the new type, keeps a stale profile from holding a
     * handle in another case.
     */
    public function up(): void
    {
        Schema::ensureExtensionExists('citext');

        Schema::table('profiles', function (Blueprint $table): void {
            $table->dropIndex('profiles_lower_handle_index');
        });

        DB::statement('ALTER TABLE profiles ALTER COLUMN handle TYPE citext');
    }
};
