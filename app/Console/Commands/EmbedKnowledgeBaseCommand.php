<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\UpdateKnowledgeBaseSections;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

#[Signature('knowledge-base:embed {source=KNOWLEDGE_BASE.md : Repository-relative Markdown file to embed}')]
#[Description('Sync knowledge base sections, embedding only new or changed content')]
final class EmbedKnowledgeBaseCommand extends Command
{
    public function handle(UpdateKnowledgeBaseSections $action): int
    {
        if (! Schema::hasTable('knowledge_base_sections')) {
            $warning = 'Skipping knowledge base embedding: the knowledge_base_sections table does not exist.';
            Log::warning($warning);
            $this->components->warn($warning);

            return self::SUCCESS;
        }

        $source = $this->argument('source');

        $embedded = $action->handle($source);

        $this->components->info("Embedded {$embedded} knowledge base sections from {$source}.");

        return self::SUCCESS;
    }
}
