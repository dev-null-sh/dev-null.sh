<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Which app a device is, as the app said when it asked to be approved. The name, like "/dev/null for Mac", is shown so
     * the user can tell apps apart, not only computers and phones. The id, its platform and bundle id like
     * "macos:sh.dev-null.mac", is the stable way to tell apps apart: names change, ids don't, and a list of official apps
     * will build on them. Apps that don't say leave both empty. Only the user sees them: the name on the approval page and
     * under Settings → Devices, both in their export. They go with the device when it is revoked or the account deleted.
     */
    public function up(): void
    {
        Schema::table('devices', function (Blueprint $table): void {
            $table->string('app_id', 100)->nullable();
            $table->string('app_name')->nullable();
        });
    }
};
