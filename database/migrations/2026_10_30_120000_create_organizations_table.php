<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * What only organizations have on /dev/null, like their color, apart from the GitHub identity on their profile, as
     * users have theirs. An organization shares its profile's ID, which mentions, links and members already point to.
     * Every organization with a profile has one, colors included. Everyone sees its color on its page. It goes with
     * the profile, when GitHub no longer has the organization.
     */
    public function up(): void
    {
        // The migration runs in one transaction. Profiles and members wait until it commits, so no organization or
        // color lands between the copy and the drop. The drop and the new foreign key need these locks anyway: taking
        // them first leaves a request already writing either only a moment to deadlock with it, rather than the whole
        // migration, and Postgres would roll it back...
        DB::statement('lock table profiles, organization_members in access exclusive mode');

        Schema::create('organizations', function (Blueprint $table): void {
            $table->foreignUuid('id')->primary()->constrained('profiles');
            $table->unsignedTinyInteger('preferred_lane')->nullable();
            $table->string('lane_color', 7)->nullable();
            $table->timestamps();
        });

        DB::table('organizations')->insertUsing(
            ['id', 'preferred_lane', 'lane_color', 'created_at', 'updated_at'],
            DB::table('profiles')->where('type', 'organization')->select(['id', 'preferred_lane', 'lane_color', 'created_at', 'updated_at']),
        );

        Schema::table('organization_members', function (Blueprint $table): void {
            $table->dropForeign(['organization_id']);
            $table->foreign('organization_id')->references('id')->on('organizations');
        });

        Schema::table('profiles', function (Blueprint $table): void {
            $table->dropColumn(['preferred_lane', 'lane_color']);
        });
    }
};
