<?php

declare(strict_types=1);

use App\Actions\ClearStaleTagVerifications;
use App\Contracts\Services\DnsResolver;
use App\Enums\TagRole;
use App\Models\Commit;
use App\Models\Tag;
use App\Models\User;
use App\Services\TagDnsVerification;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    $this->withoutVite();
});

it('shows latest and trending tag feeds', function (): void {
    $author = User::factory()->create();
    Commit::factory()->for($author)->create([
        'body' => 'Hot #laravel',
        'branch' => 'laravel',
        'hot_score' => 10,
        'created_at' => now()->subMinute(),
    ]);
    $latest = Commit::factory()->for($author)->create([
        'body' => 'New #laravel',
        'branch' => 'laravel',
        'hot_score' => 1,
    ]);

    $this->actingAs($author)->get(route('tag.show', 'laravel'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('sort', 'latest')
            ->where('commits.data.0.id', $latest->id));

    $this->get(route('tag.show', ['tag' => 'laravel', 'sort' => 'trending']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('sort', 'trending')
            ->where('isRanked', true)
            ->where('commits.data.0.hot_score', 10));
});

it('claims a tag, requests verification, and watches activity after approval', function (): void {
    $owner = User::factory()->create(['username' => 'taylor']);
    $admin = User::factory()->create(['username' => 'admin']);
    $watcher = User::factory()->create();
    config(['app.admins' => ['taylor', 'admin']]);

    $this->actingAs($owner)->post(route('tag-claim.store', 'laravel'), [
        'domain' => 'laravel.com',
    ])->assertRedirect(route('tag.settings', 'laravel'));

    $tag = Tag::query()->where('name', 'laravel')->sole();
    expect($tag->isMaintainedBy($owner))->toBeTrue()
        ->and($tag->isVerified())->toBeFalse();

    $expected = resolve(TagDnsVerification::class)->recordValue($tag);

    $this->app->instance(DnsResolver::class, new readonly class($expected) implements DnsResolver
    {
        public function __construct(private string $txt)
        {
            //
        }

        public function resolve(string $host): array
        {
            return [];
        }

        public function txt(string $host): array
        {
            return $host === '_devnull-tag.laravel.com' ? [$this->txt] : [];
        }
    });

    $this->actingAs($owner)->post(route('tag-verification.store', 'laravel'))
        ->assertRedirect();

    expect($tag->refresh()->isVerified())->toBeFalse()
        ->and($tag->hasPendingVerification())->toBeTrue();

    $this->actingAs($admin)->get(route('tag-verification-request.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('moderation/verifications')
            ->where('verifications.data.0.name', 'laravel')
            ->where('verifications.data.0.txt.matches', true)
            ->where('verifications.data.0.txt.host', '_devnull-tag.laravel.com'));

    Http::fake([
        'laravel.com/*' => Http::response(
            '<html><meta name="description" content="Laravel"><meta property="og:image" content="https://laravel.com/og.png"></html>',
            200,
            ['Content-Type' => 'text/html'],
        ),
        '*' => Http::response('', 404),
    ]);

    $this->actingAs($admin)->post(route('tag-verification-approval.store', 'laravel'))
        ->assertRedirect();

    expect($tag->refresh()->isVerified())->toBeTrue()
        ->and($tag->verified_domain)->toBe('laravel.com')
        ->and($tag->og_image_url)->toBe('https://laravel.com/og.png')
        ->and($tag->hasPendingVerification())->toBeFalse();

    $this->actingAs($watcher)->put(route('tag-watch.store', 'laravel'))->assertRedirect();

    $this->actingAs($owner)->post('/commits', [
        'body' => 'Release notes',
        'branch' => 'laravel',
    ])->assertRedirect();

    expect($watcher->notifications()->where('type', 'tag_activity')->count())->toBe(1)
        ->and($owner->notifications()->where('type', 'tag_activity')->count())->toBe(0);
});

it('lets moderators reject a verification request', function (): void {
    $owner = User::factory()->create();
    $admin = User::factory()->create(['username' => 'admin']);
    config(['app.admins' => ['admin']]);

    $tag = Tag::factory()->create([
        'name' => 'php',
        'domain' => 'php.net',
        'verification_token' => 'token',
        'verification_requested_at' => now(),
    ]);
    $tag->members()->create(['user_id' => $owner->id, 'role' => TagRole::Maintainer]);

    $this->actingAs($admin)->post(route('tag-verification-rejection.store', 'php'))
        ->assertRedirect();

    expect($tag->refresh()->hasPendingVerification())->toBeFalse()
        ->and($tag->isVerified())->toBeFalse();
});

it('clears verification when the TXT record is removed', function (): void {
    $tag = Tag::factory()->verified('laravel.com')->create(['name' => 'laravel']);

    $this->app->instance(DnsResolver::class, new readonly class implements DnsResolver
    {
        public function resolve(string $host): array
        {
            return [];
        }

        public function txt(string $host): array
        {
            return [];
        }
    });

    expect(resolve(ClearStaleTagVerifications::class)->handle())->toBe(1)
        ->and($tag->refresh()->isVerified())->toBeFalse();
});

it('lets only matching Remotes org maintainers claim and verify a branch', function (): void {
    $orgMaintainer = User::factory()->create();
    $orgContributor = User::factory()->create();
    $outsider = User::factory()->create();
    $orgContributor->following()->attach($orgMaintainer);

    createOrg($orgMaintainer, [$orgContributor], name: 'laravel');

    $this->actingAs($outsider)->post(route('tag-claim.store', 'laravel'), [
        'domain' => 'laravel.com',
    ])->assertSessionHasErrors('tag');

    $this->actingAs($orgContributor)->post(route('tag-claim.store', 'laravel'), [
        'domain' => 'laravel.com',
    ])->assertSessionHasErrors('tag');

    $this->actingAs($orgMaintainer)->post(route('tag-claim.store', 'laravel'), [
        'domain' => 'laravel.com',
    ])->assertRedirect(route('tag.settings', 'laravel'));

    $tag = Tag::query()->where('name', 'laravel')->sole();
    $expected = resolve(TagDnsVerification::class)->recordValue($tag);

    $this->app->instance(DnsResolver::class, new readonly class($expected) implements DnsResolver
    {
        public function __construct(private string $txt)
        {
            //
        }

        public function resolve(string $host): array
        {
            return [];
        }

        public function txt(string $host): array
        {
            return $host === '_devnull-tag.laravel.com' ? [$this->txt] : [];
        }
    });

    $tag->members()->create(['user_id' => $outsider->id, 'role' => TagRole::Maintainer]);

    $this->actingAs($outsider)->post(route('tag-verification.store', 'laravel'))
        ->assertForbidden();

    $this->actingAs($orgMaintainer)->post(route('tag-verification.store', 'laravel'))
        ->assertRedirect();

    expect($tag->refresh()->hasPendingVerification())->toBeTrue();
});

it('does not lock a branch when no Remotes org shares its name', function (): void {
    $claimer = User::factory()->create();
    $member = User::factory()->create();
    $member->following()->attach($claimer);

    createOrg($claimer, [$member], name: 'dev/null core');

    $this->actingAs($claimer)->post(route('tag-claim.store', 'laravel'), [
        'domain' => 'laravel.com',
    ])->assertRedirect(route('tag.settings', 'laravel'));
});

it('lets maintainers add contributors from their follow graph', function (): void {
    $maintainer = User::factory()->create();
    $friend = User::factory()->create(['username' => 'nuno']);
    $stranger = User::factory()->create(['username' => 'stranger']);
    $maintainer->following()->attach($friend);

    $tag = Tag::factory()->create(['name' => 'php']);
    $tag->members()->create(['user_id' => $maintainer->id, 'role' => TagRole::Maintainer]);

    $this->actingAs($maintainer)->post(route('tag-member.store', 'php'), [
        'members' => ['nuno'],
    ])->assertRedirect();

    expect($tag->members()->where('user_id', $friend->id)->exists())->toBeTrue()
        ->and($friend->notifications()->where('type', 'tag_member_added')->count())->toBe(1);

    $this->post(route('tag-member.store', 'php'), [
        'members' => ['stranger'],
    ])->assertSessionHasErrors('members.0');
});
