<?php

declare(strict_types=1);

use App\Actions\CreateCommit;
use App\Actions\UpdateLinkPreview;
use App\Contracts\Services\DnsResolver;
use App\Jobs\UnfurlLink;
use App\Models\Commit;
use App\Models\LinkPreview;
use App\Models\User;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;

beforeEach(function (): void {
    Queue::fake();
    $this->app->instance(DnsResolver::class, new readonly class implements DnsResolver
    {
        private const array HOSTS = [
            'example.com' => ['93.184.216.34'],
            'www.example.com' => ['93.184.216.35'],
            'giphy.com' => ['104.16.105.123'],
            'mixed.example.com' => ['93.184.216.34', '64:ff9b:2::7f00:1'],
            'nat64.example.com' => ['64:ff9b:2::7f00:1'],
        ];

        public function resolve(string $host): array
        {
            return self::HOSTS[$host] ?? [];
        }
    });
});

function draftPreviewUrl(string $url): string
{
    return '/draft-link-preview?'.http_build_query(['url' => $url]);
}

function fetchQueuedDraftPreview(): LinkPreview
{
    $preview = LinkPreview::query()->sole();
    Queue::assertPushed(UnfurlLink::class, fn (UnfurlLink $job): bool => $job->linkPreview->is($preview));
    new UnfurlLink($preview)->handle(resolve(UpdateLinkPreview::class));

    return $preview->refresh();
}

it('requires authentication', function (): void {
    Log::spy();
    $this->getJson(draftPreviewUrl('https://example.com/cat.gif'))->assertUnauthorized();

    Http::assertNothingSent();
    Queue::assertNothingPushed();
    Log::shouldNotHaveReceived('info');
    expect(LinkPreview::query()->count())->toBe(0);
});

it('validates draft link preview URLs before creating cache entries', function (mixed $url): void {
    Log::spy();
    $this->actingAs(User::factory()->create())
        ->getJson('/draft-link-preview', ['url' => $url])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('url');

    Http::assertNothingSent();
    Queue::assertNothingPushed();
    Log::shouldNotHaveReceived('info');
    expect(LinkPreview::query()->count())->toBe(0);
})->with([
    'missing' => null,
    'empty' => '',
    'unsupported protocol' => 'ftp://example.com/cat.gif',
    'too long' => 'https://example.com/'.str_repeat('x', 2048),
]);

it('returns a Giphy embed without fetching or caching a draft', function (string $host): void {
    $url = 'https://'.$host.'/gifs/JIX9t2j0ZTN9S';

    $this->actingAs(User::factory()->create())
        ->getJson(draftPreviewUrl($url))
        ->assertOk()
        ->assertHeader('Cache-Control', 'no-store, private')
        ->assertExactJson(['link_preview' => [
            'url' => $url,
            'title' => null,
            'description' => null,
            'image_url' => null,
            'site_name' => null,
            'is_pending' => false,
            'embed' => [
                'provider' => 'GIPHY',
                'kind' => 'gif',
                'src' => 'https://media.giphy.com/media/JIX9t2j0ZTN9S/giphy.gif',
                'aspect_ratio' => null,
                'height' => null,
                'poster' => 'https://media.giphy.com/media/JIX9t2j0ZTN9S/giphy_s.gif',
            ],
        ]]);

    Http::assertNothingSent();
    Queue::assertNothingPushed();
    expect(LinkPreview::query()->count())->toBe(0);
})->with(['giphy.com', 'www.giphy.com']);

it('queues a plain Giphy card when GIF embeds are unavailable', function (array $config): void {
    config($config);
    $url = 'https://giphy.com/gifs/JIX9t2j0ZTN9S';
    Http::fake([$url => Http::response('<title>Giphy card</title>', 200, ['Content-Type' => 'text/html'])]);

    $this->actingAs(User::factory()->create())
        ->getJson(draftPreviewUrl($url))->assertOk()->assertJsonPath('link_preview.is_pending', true);

    Http::assertNothingSent();
    fetchQueuedDraftPreview();
    $this->getJson(draftPreviewUrl($url))
        ->assertOk()
        ->assertJsonPath('link_preview.title', 'Giphy card')
        ->assertJsonPath('link_preview.embed', null);

    Http::assertSentCount(1);
})->with([
    'embeds disabled' => [['embeds.enabled' => false]],
    'Giphy excluded' => [['embeds.only' => ['youtube']]],
]);

it('queues uncached previews without resolving DNS or publishing content', function (): void {
    $this->app->instance(DnsResolver::class, new readonly class implements DnsResolver
    {
        public function resolve(string $host): array
        {
            throw new RuntimeException('DNS must run on the queue worker.');
        }
    });
    $url = 'https://example.com/cat.gif';

    $this->actingAs(User::factory()->create())
        ->getJson(draftPreviewUrl($url).'&gif_url=https%3A%2F%2Fevil.example%2Fcat.gif')
        ->assertOk()
        ->assertExactJson(['link_preview' => [
            'url' => $url,
            'title' => null,
            'description' => null,
            'image_url' => null,
            'site_name' => null,
            'is_pending' => true,
            'embed' => null,
        ]]);

    Http::assertNothingSent();
    $preview = LinkPreview::query()->sole();
    expect($preview->url_hash)->toBe(hash('sha256', $url))
        ->and($preview->gif_url)->toBeNull()
        ->and(Commit::query()->count())->toBe(0);
    Queue::assertPushed(UnfurlLink::class, fn (UnfurlLink $job): bool => $job->linkPreview->is($preview));
});

it('returns verified GIFs from the shared cache after the queued fetch', function (bool $redirect): void {
    $source = 'https://example.com/cat.gif';
    $final = $redirect ? 'https://www.example.com/media?id=cat' : $source;
    Http::fake([
        $source => $redirect
            ? Http::response(status: 302, headers: ['Location' => $final])
            : Http::response("GIF89a\x01\x00\x01\x00\x80\x00\x00", 206, ['Content-Type' => 'image/gif']),
        ...($redirect ? [$final => Http::response("GIF89a\x01\x00\x01\x00\x80\x00\x00", 206, ['Content-Type' => 'image/gif'])] : []),
    ]);

    $this->actingAs(User::factory()->create())
        ->getJson(draftPreviewUrl($source))->assertOk()->assertJsonPath('link_preview.is_pending', true);
    Http::assertNothingSent();
    fetchQueuedDraftPreview();

    $this->getJson(draftPreviewUrl($source))
        ->assertOk()
        ->assertJsonPath('link_preview.url', $source)
        ->assertJsonPath('link_preview.is_pending', false)
        ->assertJsonPath('link_preview.embed.provider', 'GIF')
        ->assertJsonPath('link_preview.embed.kind', 'gif')
        ->assertJsonPath('link_preview.embed.src', $final)
        ->assertJsonPath('link_preview.embed.aspect_ratio', null);

    Http::assertSentCount($redirect ? 2 : 1);
    Queue::assertPushed(UnfurlLink::class, 1);
    expect(LinkPreview::query()->count())->toBe(1);
})->with(['direct' => false, 'extensionless redirect' => true]);

it('does not create an embed when the queued GIF check fails', function (): void {
    $url = 'https://example.com/cat.gif';
    Http::fake([$url => Http::response('<title>Not a GIF</title>', 200, ['Content-Type' => 'image/gif'])]);

    $this->actingAs(User::factory()->create())->getJson(draftPreviewUrl($url))->assertOk();
    fetchQueuedDraftPreview();
    $this->getJson(draftPreviewUrl($url))
        ->assertOk()->assertJsonPath('link_preview.is_pending', false)->assertJsonPath('link_preview.embed', null);
});

it('keeps the shared address guard on queued draft fetches', function (string $host): void {
    $url = 'https://'.$host.'/cat.gif';
    $this->actingAs(User::factory()->create())->getJson(draftPreviewUrl($url))->assertOk();
    fetchQueuedDraftPreview();

    $this->getJson(draftPreviewUrl($url))
        ->assertOk()->assertJsonPath('link_preview.is_pending', false)->assertJsonPath('link_preview.embed', null);
    Http::assertNothingSent();
})->with(['mixed.example.com', 'nat64.example.com']);

it('does not follow queued redirects to an unsafe DNS destination', function (): void {
    $url = 'https://example.com/cat.gif';
    Http::fake([$url => Http::response(status: 302, headers: ['Location' => 'https://nat64.example.com/cat.gif'])]);

    $this->actingAs(User::factory()->create())->getJson(draftPreviewUrl($url))->assertOk();
    fetchQueuedDraftPreview();
    $this->getJson(draftPreviewUrl($url))->assertOk()->assertJsonPath('link_preview.embed', null);
    Http::assertSentCount(1);
});

it('leaves a transient failure pending for the existing job retry policy', function (): void {
    $url = 'https://example.com/cat.gif';
    Http::fake([$url => Http::failedConnection()]);

    $this->actingAs(User::factory()->create())->getJson(draftPreviewUrl($url))->assertOk();
    expect(fn (): LinkPreview => fetchQueuedDraftPreview())->toThrow(ConnectionException::class);
    $this->getJson(draftPreviewUrl($url))->assertOk()->assertJsonPath('link_preview.is_pending', true);
    Queue::assertPushed(UnfurlLink::class, 1);
    Http::assertSentCount(1);
});

it('reuses completed metadata without an independent expiry policy', function (int $ageInDays): void {
    $preview = LinkPreview::factory()->fetched()->create([
        'url' => 'https://example.com/cat.gif',
        'gif_url' => 'https://www.example.com/media?id=cat',
        'fetched_at' => now()->subDays($ageInDays),
    ]);
    $original = $preview->refresh()->getAttributes();

    $this->actingAs(User::factory()->create())
        ->getJson(draftPreviewUrl($preview->url))
        ->assertOk()
        ->assertJsonPath('link_preview.title', $preview->title)
        ->assertJsonPath('link_preview.embed.src', $preview->gif_url)
        ->assertJsonPath('link_preview.is_pending', false);

    Http::assertNothingSent();
    Queue::assertNothingPushed();
    expect($preview->fresh()->getAttributes())->toBe($original)
        ->and(LinkPreview::query()->count())->toBe(1);
})->with([0, 30]);

it('does not retry a terminal empty cache row just because a draft polls it', function (): void {
    $preview = LinkPreview::factory()->failed()->create(['url' => 'https://example.com/unavailable']);
    $this->actingAs(User::factory()->create());

    foreach (range(1, 7) as $attempt) {
        $this->getJson(draftPreviewUrl($preview->url))
            ->assertOk()
            ->assertJsonPath('link_preview.is_pending', false)
            ->assertJsonPath('link_preview.title', null)
            ->assertJsonPath('link_preview.embed', null);
    }

    Queue::assertNothingPushed();
    Http::assertNothingSent();
});

it('reuses pending rows and polling never enqueues duplicate work', function (): void {
    $url = 'https://example.com/article';
    $this->actingAs(User::factory()->create());
    foreach (range(1, 7) as $attempt) {
        $this->getJson(draftPreviewUrl($url))->assertOk()->assertJsonPath('link_preview.is_pending', true);
    }

    Queue::assertPushed(UnfurlLink::class, 1);
    Http::assertNothingSent();
    expect(LinkPreview::query()->count())->toBe(1);
});

it('dispatches only after its cache transaction commits', function (): void {
    $transactionLevel = DB::transactionLevel();
    Bus::shouldReceive('dispatch')->once()
        ->withArgs(fn (UnfurlLink $job): bool => DB::transactionLevel() === $transactionLevel)
        ->andReturnNull();

    $this->actingAs(User::factory()->create())
        ->getJson(draftPreviewUrl('https://example.com/article'))->assertOk();
});

it('ends the pending state when the real queue driver cannot enqueue the job', function (): void {
    config(['queue.default' => 'database']);
    Queue::swap(Queue::fake()->queue);
    DB::connection()->beforeExecuting(function (string $query): void {
        throw_if(str_starts_with($query, 'insert into "jobs"'), RuntimeException::class, 'Queue unavailable');
    });
    $this->withoutExceptionHandling();
    $user = User::factory()->create();
    $this->actingAs($user);

    expect(fn () => $this->getJson(draftPreviewUrl('https://example.com/article')))
        ->toThrow(RuntimeException::class, 'Queue unavailable');

    expect(LinkPreview::query()->sole()->fetched_at)->not->toBeNull()
        ->and(DB::table('jobs')->count())->toBe(0)
        ->and(RateLimiter::attempts('draft-link-unfurls:'.$user->id))->toBe(1);
    Http::assertNothingSent();
});

it('runs an uncached preview on the database queue and returns it on polling', function (): void {
    config(['queue.default' => 'database']);
    Queue::swap(Queue::fake()->queue);
    $url = 'https://example.com/article';
    Http::fake([$url => Http::response('<title>Queued article</title>', 200, ['Content-Type' => 'text/html'])]);

    $this->actingAs(User::factory()->create())
        ->getJson(draftPreviewUrl($url))->assertOk()->assertJsonPath('link_preview.is_pending', true);
    Http::assertNothingSent();

    expect(DB::table('jobs')->count())->toBe(1);
    $this->artisan('queue:work', ['connection' => 'database', '--once' => true])->assertSuccessful();
    expect(DB::table('failed_jobs')->count())->toBe(0);
    Http::assertSentCount(1);
    $this->getJson(draftPreviewUrl($url))
        ->assertOk()->assertJsonPath('link_preview.title', 'Queued article')->assertJsonPath('link_preview.is_pending', false);
    Http::assertSentCount(1);
});

it('looks up only the requested URL hash', function (): void {
    LinkPreview::factory()->fetched()->create(['url' => 'https://example.com/other']);
    $url = 'https://example.com/article';
    $this->actingAs(User::factory()->create())
        ->getJson(draftPreviewUrl($url))->assertOk()->assertJsonPath('link_preview.is_pending', true);
    expect(LinkPreview::query()->count())->toBe(2);
    Queue::assertPushed(UnfurlLink::class, fn (UnfurlLink $job): bool => $job->linkPreview->url === $url);
    Http::assertNothingSent();
});

it('records the authenticated user and host without logging draft URL secrets', function (): void {
    Log::spy();
    $user = User::factory()->create();
    $this->actingAs($user)
        ->getJson(draftPreviewUrl('https://example.com/cat.gif?token=private-draft-token'))->assertOk();
    Log::shouldHaveReceived('info')->once()->with('Draft link preview requested', [
        'user_id' => $user->id,
        'host' => 'example.com',
    ]);
    Http::assertNothingSent();
});

it('reuses the fetched draft metadata when the URL is published', function (): void {
    $url = 'https://example.com/cat.gif';
    Http::fake([$url => Http::response("GIF89a\x01\x00\x01\x00\x80\x00\x00", 206, ['Content-Type' => 'image/gif'])]);
    $user = User::factory()->create();
    $this->actingAs($user)->getJson(draftPreviewUrl($url))->assertOk();
    $preview = fetchQueuedDraftPreview();
    Queue::fake();

    $commit = resolve(CreateCommit::class)->handle($user, 'Look at '.$url);
    expect($commit->link_preview_id)->toBe($preview->id)
        ->and(LinkPreview::query()->count())->toBe(1);
    Queue::assertNotPushed(UnfurlLink::class);
    Http::assertSentCount(1);
});

it('limits new unfurls without charging cached polls to that budget', function (): void {
    $this->actingAs(User::factory()->create());
    foreach (range(1, 10) as $attempt) {
        $this->getJson(draftPreviewUrl('https://example.com/article/'.$attempt))->assertOk();
    }

    $blocked = 'https://example.com/another';
    $this->getJson(draftPreviewUrl($blocked))->assertTooManyRequests()->assertHeader('Retry-After');
    expect(LinkPreview::query()->count())->toBe(10);

    foreach (range(1, 7) as $attempt) {
        $this->getJson(draftPreviewUrl('https://example.com/article/1'))->assertOk();
    }

    Queue::assertPushed(UnfurlLink::class, 10);
    $this->actingAs(User::factory()->create())->getJson(draftPreviewUrl($blocked))->assertOk();
    Queue::assertPushed(UnfurlLink::class, 11);
    Http::assertNothingSent();
});

it('allows two pending cards to complete their polling cycles in one minute', function (): void {
    $this->actingAs(User::factory()->create());
    foreach (['https://example.com/first', 'https://example.com/second'] as $url) {
        foreach (range(1, 7) as $attempt) {
            $this->getJson(draftPreviewUrl($url))->assertOk()->assertJsonPath('link_preview.is_pending', true);
        }
    }

    Queue::assertPushed(UnfurlLink::class, 2);
    Http::assertNothingSent();
});

it('allows new unfurls again after the work budget expires', function (): void {
    $this->actingAs(User::factory()->create());
    foreach (range(1, 10) as $attempt) {
        $this->getJson(draftPreviewUrl('https://example.com/article/'.$attempt))->assertOk();
    }

    $url = 'https://example.com/after-limit';
    $this->getJson(draftPreviewUrl($url))->assertTooManyRequests();
    $this->travel(61)->seconds();
    $this->getJson(draftPreviewUrl($url))->assertOk();
    Queue::assertPushed(UnfurlLink::class, 11);
});

it('limits preview reads separately from new work', function (): void {
    $this->actingAs(User::factory()->create());
    $url = draftPreviewUrl('https://giphy.com/gifs/JIX9t2j0ZTN9S');
    foreach (range(1, 120) as $attempt) {
        $this->getJson($url)->assertOk();
    }

    $this->getJson($url)->assertTooManyRequests();
    Queue::assertNothingPushed();
    Http::assertNothingSent();
});
