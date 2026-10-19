<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('search_syncs', function (Blueprint $table): void {
            $table->id();
            $table->string('index');
            $table->string('object_id');

            $table->index(['index', 'id']);
        });
    }
};
