<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\UpdateKnowledgeBaseSections;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('knowledge-base:embed {source=KNOWLEDGE_BASE.md : Repository-relative Markdown file to embed}')]
#[Description('Sync knowledge base sections, embedding only new or changed content')]
final class EmbedKnowledgeBaseCommand extends Command
{
    public function handle(UpdateKnowledgeBaseSections $action): int
    {
        $source = $this->argument('source');

        $embedded = $action->handle($source);

        $this->components->info("Embedded {$embedded} knowledge base sections from {$source}.");

        return self::SUCCESS;
    }
}
