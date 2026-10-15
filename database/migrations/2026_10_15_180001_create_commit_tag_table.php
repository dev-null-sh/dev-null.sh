<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('commit_tag', function (Blueprint $table): void {
            $table->foreignUuid('tag_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('commit_id')->constrained()->cascadeOnDelete();

            $table->primary(['tag_id', 'commit_id']);
            $table->index('commit_id');
        });
    }
};
