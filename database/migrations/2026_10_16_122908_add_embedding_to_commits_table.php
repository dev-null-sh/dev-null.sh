<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Commits are embedded with text-embedding-3-small, so the vector has 1536 dimensions. The HNSW index makes the
     * nearest-neighbour lookup the search runs fast, without the training step an IVFFlat index would need.
     */
    public function up(): void
    {
        Schema::ensureVectorExtensionExists();

        Schema::table('commits', function (Blueprint $table): void {
            $table->vector('embedding', dimensions: 1536)->nullable();
            $table->vectorIndex('embedding', 'commits_embedding_index');
        });
    }
};
