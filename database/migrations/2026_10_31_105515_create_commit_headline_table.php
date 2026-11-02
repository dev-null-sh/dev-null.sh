<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Each commit can have several headlines, each only once. The link's creation time determines headline expiry without
     * storing a duplicate last-used timestamp. Links disappear when either side is permanently deleted.
     */
    public function up(): void
    {
        Schema::create('commit_headline', function (Blueprint $table): void {
            $table->foreignUuid('commit_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('headline_id')->constrained()->cascadeOnDelete();
            $table->timestamp('created_at');

            $table->primary(['commit_id', 'headline_id']);
            $table->index(['headline_id', 'created_at']);
        });
    }
};
