<?php

declare(strict_types=1);

namespace App\Actions;

use App\Contracts\Services\EmbeddingProvider;
use App\Models\KnowledgeBaseSection;
use App\Services\MarkdownSections;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

final readonly class UpdateKnowledgeBaseSections
{
    public function __construct(private MarkdownSections $markdownSections, private EmbeddingProvider $embeddings)
    {
        //
    }

    /**
     * Sections whose heading context and content are unchanged reuse their stored vector, even if they moved.
     */
    public function handle(string $source): int
    {
        $sections = collect($this->markdownSections->split(File::get(base_path($source))));

        $stored = KnowledgeBaseSection::query()->where('source', $source)->get()
            ->mapWithKeys(fn (KnowledgeBaseSection $section): array => [$this->key($section->headings, $section->content) => $section->embedding]);

        $embeddings = $sections->map(fn (array $section): ?array => $stored->get($this->key($section['headings'], $section['content'])));
        $changed = $sections->filter(fn (array $section, int $position): bool => $embeddings[$position] === null);

        foreach ($changed->chunk(100) as $batch) {
            $embeddings = $embeddings->replace($batch->keys()->combine($this->embeddings->embedMany(
                array_values($batch->map(fn (array $section): string => mb_trim(implode(' > ', $section['headings'])."\n\n".$section['content']))->all()),
            )));
        }

        DB::transaction(function () use ($source, $sections, $embeddings): void {
            $sections->each(fn (array $section, int $position): KnowledgeBaseSection => KnowledgeBaseSection::query()->updateOrCreate(
                ['source' => $source, 'position' => $position],
                [...$section, 'embedding' => $embeddings[$position]],
            ));

            KnowledgeBaseSection::query()->where('source', $source)
                ->whereNotIn('position', $sections->keys())
                ->delete();
        });

        return $changed->count();
    }

    /**
     * @param  list<string>  $headings
     */
    private function key(array $headings, string $content): string
    {
        return json_encode([$headings, $content], JSON_THROW_ON_ERROR);
    }
}
