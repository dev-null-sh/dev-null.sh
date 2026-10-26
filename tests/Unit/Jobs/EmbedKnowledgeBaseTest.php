<?php

declare(strict_types=1);

use App\Jobs\EmbedKnowledgeBase;
use App\Models\KnowledgeBaseSection;
use Illuminate\Console\Scheduling\CallbackEvent;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

beforeEach(function (): void {
    config(['ai.providers.openai.key' => 'sk-test']);

    $disk = Storage::fake('local');
    $this->source = Str::after($disk->path('knowledge.md'), base_path().'/');
});

it('embeds the knowledge base sections of its source', function (): void {
    Storage::disk('local')->put('knowledge.md', "# Lore\n## Tom\nA fictional profile.");
    Http::fake(['api.openai.com/v1/embeddings' => Http::response(embeddingsResponse([embeddingVector(1)]))]);

    dispatch_sync(new EmbedKnowledgeBase($this->source));

    expect(KnowledgeBaseSection::query()->sole())->source->toBe($this->source)
        ->headings->toBe(['Lore', 'Tom'])
        ->content->toBe('A fictional profile.')
        ->embedding->toBe(embeddingVector(1));
});

it('throws when openai is unavailable, so the job is retried, keeping the stored sections', function (): void {
    $existing = KnowledgeBaseSection::factory()->create(['source' => $this->source]);
    Storage::disk('local')->put('knowledge.md', "# Lore\nUpdated text.");
    Http::fake(['api.openai.com/v1/embeddings' => Http::response([], 503)]);

    expect(fn () => dispatch_sync(new EmbedKnowledgeBase($this->source)))->toThrow(RequestException::class);

    expect($existing->fresh()->getAttributes())->toEqual($existing->getAttributes());
});

it('is unique per source', function (): void {
    expect(new EmbedKnowledgeBase()->uniqueId())->toBe('KNOWLEDGE_BASE.md')
        ->and(new EmbedKnowledgeBase('other.md')->uniqueId())->toBe('other.md');
});

it('is dispatched every hour for the knowledge base', function (): void {
    $event = collect(resolve(Schedule::class)->events())
        ->sole(fn (Event $event): bool => $event->description === EmbedKnowledgeBase::class);

    expect($event)->toBeInstanceOf(CallbackEvent::class)
        ->expression->toBe('0 * * * *');
});
