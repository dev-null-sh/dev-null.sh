<?php

declare(strict_types=1);

use App\Actions\UpdateStaleHandles;
use App\Models\Profile;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * GitHub handles are unique whatever their case, so profiles' are too: comparing them ignores case, so looking one
     * up needs no lowercasing, and a stale profile can't keep a handle in another case. Profiles already holding one
     * in another case leave it to the one GitHub synced last and get the placeholder a sync gives them.
     */
    public function up(): void
    {
        Schema::ensureExtensionExists('citext');

        Schema::table('profiles', function (Blueprint $table): void {
            $table->dropUnique(['handle']);
            $table->dropIndex('profiles_lower_handle_index');
        });

        DB::statement('ALTER TABLE profiles ALTER COLUMN handle TYPE citext');

        Profile::query()
            ->whereIn('handle', Profile::query()->select('handle')->groupBy('handle')->havingRaw('COUNT(*) > 1'))
            ->orderByRaw('github_synced_at DESC NULLS LAST')
            ->latest('updated_at')
            ->get()
            ->unique(fn (Profile $profile): string => mb_strtolower($profile->handle))
            ->each(fn (Profile $profile) => resolve(UpdateStaleHandles::class)->handle($profile->handle, $profile->github_id));

        Schema::table('profiles', function (Blueprint $table): void {
            $table->unique('handle');
        });
    }
};
