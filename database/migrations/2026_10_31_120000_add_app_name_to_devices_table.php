<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The name the app gave itself when it asked to be approved, like "/dev/null for Mac", so the user can tell which app
     * a device is, not only which computer or phone. Apps that don't say leave it empty. Only the user sees it, on the
     * approval page and under Settings → Devices, and it goes with the device when it is revoked or the account deleted.
     */
    public function up(): void
    {
        Schema::table('devices', function (Blueprint $table): void {
            $table->string('app_name')->nullable();
        });
    }
};
