<?php

declare(strict_types=1);

use App\Contracts\Services\EmbeddingProvider;
use App\Models\KnowledgeBaseSection;
use App\Queries\KnowledgeBaseSearchQuery;
use GuzzleHttp\Psr7\Response as Psr7Response;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

beforeEach(function (): void {
    $disk = Storage::fake('local');
    $this->source = Str::after($disk->path('knowledge.md'), base_path().'/');
});

it('stores sections and their vectors with heading context and can retrieve the nearest section', function (): void {
    Storage::disk('local')->put('knowledge.md', "# Knowledge base\n\n## Lore\n\n### Tom\nA fictional profile.\n\n### Pastéis\nPortuguese pastries.");
    $embeddings = $this->mock(EmbeddingProvider::class);
    $embeddings->shouldReceive('embedMany')->once()->with([
        "Knowledge base > Lore > Tom\n\nA fictional profile.",
        "Knowledge base > Lore > Pastéis\n\nPortuguese pastries.",
    ])->andReturn([embeddingVector(2), embeddingVector(7)]);
    $embeddings->shouldReceive('embed')->once()->with('Portuguese pastry')->andReturn(embeddingVector(7));

    $this->artisan('knowledge-base:embed', ['source' => $this->source])
        ->expectsOutputToContain('Embedded 2 knowledge base sections')
        ->assertSuccessful();

    $sections = KnowledgeBaseSection::query()->orderBy('position')->get();
    expect($sections)->toHaveCount(2);
    expect($sections[0])->source->toBe($this->source)
        ->position->toBe(0)
        ->headings->toBe(['Knowledge base', 'Lore', 'Tom'])
        ->content->toBe('A fictional profile.')
        ->embedding->toBe(embeddingVector(2));
    expect($sections[1])->position->toBe(1)
        ->headings->toBe(['Knowledge base', 'Lore', 'Pastéis'])
        ->content->toBe('Portuguese pastries.')
        ->embedding->toBe(embeddingVector(7));

    expect(resolve(KnowledgeBaseSearchQuery::class)->get('Portuguese pastry', limit: 1)->sole()->id)
        ->toBe($sections[1]->id);
});

it('replaces changed and removed sections without duplicating them or touching other sources', function (): void {
    $other = KnowledgeBaseSection::factory()->create();
    Storage::disk('local')->put('knowledge.md', "# Lore\nOld introduction.\n\n## Tom\nOld purpose.");
    $embeddings = $this->mock(EmbeddingProvider::class);
    $embeddings->shouldReceive('embedMany')->twice()->andReturn([embeddingVector(0), embeddingVector(1)], [embeddingVector(3)]);
    $this->artisan('knowledge-base:embed', ['source' => $this->source])->assertSuccessful();

    Storage::disk('local')->put('knowledge.md', "# Lore\n## Tom\nDeepSeek is planned.");
    $this->artisan('knowledge-base:embed', ['source' => $this->source])->assertSuccessful();
    $this->artisan('knowledge-base:embed', ['source' => $this->source])
        ->expectsOutputToContain('Embedded 0 knowledge base sections')
        ->assertSuccessful();

    $this->assertDatabaseCount('knowledge_base_sections', 2);
    $section = KnowledgeBaseSection::query()->where('source', $this->source)->sole();
    expect($section)->position->toBe(0)
        ->headings->toBe(['Lore', 'Tom'])
        ->content->toBe('DeepSeek is planned.')
        ->embedding->toBe(embeddingVector(3));
    expect($other->fresh()->getAttributes())->toEqual($other->getAttributes());
});

it('preserves existing sections if embedding or storing a replacement fails', function (Closure $answer, string $exception): void {
    $existing = KnowledgeBaseSection::factory()->create(['source' => $this->source]);
    Storage::disk('local')->put('knowledge.md', "# Lore\n## Tom\nUpdated text.\n## Pastéis\nMore text.");
    $embeddings = $this->mock(EmbeddingProvider::class);
    $embeddings->shouldReceive('embedMany')->andReturnUsing($answer);

    expect(fn () => $this->artisan('knowledge-base:embed', ['source' => $this->source])->run())->toThrow($exception);

    $this->assertDatabaseCount('knowledge_base_sections', 1);
    expect($existing->fresh()->getAttributes())->toEqual($existing->getAttributes());
})->with([
    'provider failure' => [fn () => throw new RequestException(new Response(new Psr7Response(503))), RequestException::class],
    'missing embedding' => [fn () => throw new UnexpectedValueException('OpenAI did not return an embedding for every input.'), UnexpectedValueException::class],
    'invalid vector after a valid insert' => [fn (): array => [embeddingVector(0), [0.5, 0.5]], QueryException::class],
]);

it('removes obsolete sections when the file has no content to embed', function (): void {
    KnowledgeBaseSection::factory()->create(['source' => $this->source]);
    $other = KnowledgeBaseSection::factory()->create();
    Storage::disk('local')->put('knowledge.md', "# Knowledge base\n## Lore\n");
    $embeddings = $this->mock(EmbeddingProvider::class);
    $embeddings->shouldNotReceive('embedMany');

    $this->artisan('knowledge-base:embed', ['source' => $this->source])
        ->expectsOutputToContain('Embedded 0 knowledge base sections')
        ->assertSuccessful();

    $this->assertDatabaseCount('knowledge_base_sections', 1);
    $this->assertModelExists($other);
});

it('does not remove sections when the source file is missing', function (): void {
    $existing = KnowledgeBaseSection::factory()->create(['source' => $this->source]);
    $embeddings = $this->mock(EmbeddingProvider::class);
    $embeddings->shouldNotReceive('embedMany');

    expect(fn () => $this->artisan('knowledge-base:embed', ['source' => $this->source])->run())->toThrow(FileNotFoundException::class);

    $this->assertModelExists($existing);
});

it('keeps section positions and vectors aligned across embedding batches', function (): void {
    Storage::disk('local')->put('knowledge.md', implode("\n\n", array_map(
        fn (int $position): string => "# Section {$position}\nBody {$position}.",
        range(0, 100),
    )));
    $embeddings = $this->mock(EmbeddingProvider::class);
    $embeddings->shouldReceive('embedMany')->twice()->andReturn(array_fill(0, 100, embeddingVector(1)), [embeddingVector(2)]);

    $this->artisan('knowledge-base:embed', ['source' => $this->source])
        ->expectsOutputToContain('Embedded 101 knowledge base sections')
        ->assertSuccessful();

    $this->assertDatabaseCount('knowledge_base_sections', 101);
    expect(KnowledgeBaseSection::query()->where('position', 99)->sole()->embedding)->toBe(embeddingVector(1));
    expect(KnowledgeBaseSection::query()->where('position', 100)->sole())
        ->headings->toBe(['Section 100'])
        ->content->toBe('Body 100.')
        ->embedding->toBe(embeddingVector(2));
});

it('only embeds edited and added sections while keeping reused vectors aligned', function (): void {
    $first = KnowledgeBaseSection::factory()->create([
        'source' => $this->source, 'position' => 0, 'headings' => ['Lore', 'Tom'],
        'content' => 'A fictional profile.', 'embedding' => embeddingVector(1),
    ]);
    $edited = KnowledgeBaseSection::factory()->create([
        'source' => $this->source, 'position' => 1, 'headings' => ['Lore', 'Pastéis'],
        'content' => 'Old pastry story.', 'embedding' => embeddingVector(2),
    ]);
    KnowledgeBaseSection::factory()->create([
        'source' => $this->source, 'position' => 2, 'headings' => ['Stack'],
        'content' => 'PostgreSQL.', 'embedding' => embeddingVector(3),
    ]);
    Storage::disk('local')->put('knowledge.md', "# Lore\n## Tom\nA fictional profile.\n## Pastéis\nFernando brought pastries.\n# Stack\nPostgreSQL.\n# Hosting\nLaravel Cloud.");
    $embeddings = $this->mock(EmbeddingProvider::class);
    $embeddings->shouldReceive('embedMany')->once()->with([
        "Lore > Pastéis\n\nFernando brought pastries.", "Hosting\n\nLaravel Cloud.",
    ])->andReturn([embeddingVector(4), embeddingVector(5)]);

    $this->artisan('knowledge-base:embed', ['source' => $this->source])
        ->expectsOutputToContain('Embedded 2 knowledge base sections')
        ->assertSuccessful();

    $this->assertDatabaseCount('knowledge_base_sections', 4);
    expect($first->fresh()->getAttributes())->toEqual($first->getAttributes());
    expect($edited->refresh())->content->toBe('Fernando brought pastries.')->embedding->toBe(embeddingVector(4));
    expect(KnowledgeBaseSection::query()->orderBy('position')->get()->pluck('embedding')->all())
        ->toBe([embeddingVector(1), embeddingVector(4), embeddingVector(3), embeddingVector(5)]);
});

it('reuses moved sections but reembeds when their heading context changes', function (): void {
    KnowledgeBaseSection::factory()->create([
        'source' => $this->source, 'position' => 0, 'headings' => ['Lore', 'Tom'],
        'content' => 'A fictional profile.', 'embedding' => embeddingVector(1),
    ]);
    KnowledgeBaseSection::factory()->create([
        'source' => $this->source, 'position' => 1, 'headings' => ['Stack'],
        'content' => 'PostgreSQL.', 'embedding' => embeddingVector(2),
    ]);
    Storage::disk('local')->put('knowledge.md', "# Stack\nPostgreSQL.\n# Characters\n## Tom\nA fictional profile.");
    $embeddings = $this->mock(EmbeddingProvider::class);
    $embeddings->shouldReceive('embedMany')->once()->with(["Characters > Tom\n\nA fictional profile."])->andReturn([embeddingVector(3)]);

    $this->artisan('knowledge-base:embed', ['source' => $this->source])
        ->expectsOutputToContain('Embedded 1 knowledge base sections')
        ->assertSuccessful();

    expect(KnowledgeBaseSection::query()->orderBy('position')->get()->pluck('embedding')->all())
        ->toBe([embeddingVector(2), embeddingVector(3)]);
});
