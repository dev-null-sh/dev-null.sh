<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The color an organization's admins chose for its lane, like users': one from the palette or a custom one. Both
     * empty is /dev/null's blue, which every organization has until its admins choose.
     * Everyone sees it on the organization's page. It goes with the organization when GitHub no longer has it.
     */
    public function up(): void
    {
        Schema::table('profiles', function (Blueprint $table): void {
            $table->unsignedTinyInteger('preferred_lane')->nullable();
            $table->string('lane_color', 7)->nullable();
        });
    }
};
