<?php

declare(strict_types=1);

use App\Actions\ClearStaleBranchVerifications;
use App\Contracts\Services\DnsResolver;
use App\Enums\BranchRole;
use App\Models\Branch;
use App\Models\Commit;
use App\Models\User;
use App\Services\BranchDnsVerification;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    $this->withoutVite();
});

it('shows latest and trending branch feeds', function (): void {
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

    $this->actingAs($author)->get(route('branch.show', 'laravel'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('sort', 'latest')
            ->where('commits.data.0.id', $latest->id));

    $this->get(route('branch.show', ['branch' => 'laravel', 'sort' => 'trending']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('sort', 'trending')
            ->where('isRanked', true)
            ->where('commits.data.0.hot_score', 10));
});

it('claims a branch, requests verification, and watches activity after approval', function (): void {
    $owner = User::factory()->create(['username' => 'taylor']);
    $admin = User::factory()->create(['username' => 'admin']);
    $watcher = User::factory()->create();
    config(['app.admins' => ['taylor', 'admin']]);

    $this->actingAs($owner)->post(route('branch-claim.store', 'laravel'), [
        'domain' => 'laravel.com',
    ])->assertRedirect(route('branch.settings', 'laravel'));

    $branch = Branch::query()->where('name', 'laravel')->sole();
    expect($branch->isMaintainedBy($owner))->toBeTrue()
        ->and($branch->isVerified())->toBeFalse();

    $expected = resolve(BranchDnsVerification::class)->recordValue($branch);

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
            return $host === '_devnull-branch.laravel.com' ? [$this->txt] : [];
        }
    });

    $this->actingAs($owner)->post(route('branch-verification.store', 'laravel'))
        ->assertRedirect();

    expect($branch->refresh()->isVerified())->toBeFalse()
        ->and($branch->hasPendingVerification())->toBeTrue();

    $this->actingAs($admin)->get(route('branch-verification-request.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('moderation/verifications')
            ->where('verifications.data.0.name', 'laravel')
            ->where('verifications.data.0.txt.matches', true)
            ->where('verifications.data.0.txt.host', '_devnull-branch.laravel.com'));

    Http::fake([
        'laravel.com/*' => Http::response(
            '<html><meta name="description" content="Laravel"><meta property="og:image" content="https://laravel.com/og.png"></html>',
            200,
            ['Content-Type' => 'text/html'],
        ),
        '*' => Http::response('', 404),
    ]);

    $this->actingAs($admin)->post(route('branch-verification-approval.store', 'laravel'))
        ->assertRedirect();

    expect($branch->refresh()->isVerified())->toBeTrue()
        ->and($branch->verified_domain)->toBe('laravel.com')
        ->and($branch->og_image_url)->toBe('https://laravel.com/og.png')
        ->and($branch->hasPendingVerification())->toBeFalse();

    $this->actingAs($watcher)->put(route('branch-watch.store', 'laravel'))->assertRedirect();

    $this->actingAs($owner)->post('/commits', [
        'body' => 'Release notes',
        'branch' => 'laravel',
    ])->assertRedirect();

    expect($watcher->notifications()->where('type', 'branch_activity')->count())->toBe(1)
        ->and($owner->notifications()->where('type', 'branch_activity')->count())->toBe(0);
});

it('lets moderators reject a verification request', function (): void {
    $owner = User::factory()->create();
    $admin = User::factory()->create(['username' => 'admin']);
    config(['app.admins' => ['admin']]);

    $branch = Branch::factory()->create([
        'name' => 'php',
        'domain' => 'php.net',
        'verification_token' => 'token',
        'verification_requested_at' => now(),
    ]);
    $branch->members()->create(['user_id' => $owner->id, 'role' => BranchRole::Maintainer]);

    $this->actingAs($admin)->post(route('branch-verification-rejection.store', 'php'))
        ->assertRedirect();

    expect($branch->refresh()->hasPendingVerification())->toBeFalse()
        ->and($branch->isVerified())->toBeFalse();
});

it('clears verification when the TXT record is removed', function (): void {
    $branch = Branch::factory()->verified('laravel.com')->create(['name' => 'laravel']);

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

    expect(resolve(ClearStaleBranchVerifications::class)->handle())->toBe(1)
        ->and($branch->refresh()->isVerified())->toBeFalse();
});

it('lets only matching Remotes org maintainers claim and verify a branch', function (): void {
    $orgMaintainer = User::factory()->create();
    $orgContributor = User::factory()->create();
    $outsider = User::factory()->create();
    $orgContributor->following()->attach($orgMaintainer);

    createOrg($orgMaintainer, [$orgContributor], name: 'laravel');

    $this->actingAs($outsider)->post(route('branch-claim.store', 'laravel'), [
        'domain' => 'laravel.com',
    ])->assertSessionHasErrors('branch');

    $this->actingAs($orgContributor)->post(route('branch-claim.store', 'laravel'), [
        'domain' => 'laravel.com',
    ])->assertSessionHasErrors('branch');

    $this->actingAs($orgMaintainer)->post(route('branch-claim.store', 'laravel'), [
        'domain' => 'laravel.com',
    ])->assertRedirect(route('branch.settings', 'laravel'));

    $branch = Branch::query()->where('name', 'laravel')->sole();
    $expected = resolve(BranchDnsVerification::class)->recordValue($branch);

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
            return $host === '_devnull-branch.laravel.com' ? [$this->txt] : [];
        }
    });

    $branch->members()->create(['user_id' => $outsider->id, 'role' => BranchRole::Maintainer]);

    $this->actingAs($outsider)->post(route('branch-verification.store', 'laravel'))
        ->assertForbidden();

    $this->actingAs($orgMaintainer)->post(route('branch-verification.store', 'laravel'))
        ->assertRedirect();

    expect($branch->refresh()->hasPendingVerification())->toBeTrue();
});

it('does not lock a branch when no Remotes org shares its name', function (): void {
    $claimer = User::factory()->create();
    $member = User::factory()->create();
    $member->following()->attach($claimer);

    createOrg($claimer, [$member], name: 'dev/null core');

    $this->actingAs($claimer)->post(route('branch-claim.store', 'laravel'), [
        'domain' => 'laravel.com',
    ])->assertRedirect(route('branch.settings', 'laravel'));
});

it('lets maintainers add contributors from their follow graph', function (): void {
    $maintainer = User::factory()->create();
    $friend = User::factory()->create(['username' => 'nuno']);
    $stranger = User::factory()->create(['username' => 'stranger']);
    $maintainer->following()->attach($friend);

    $branch = Branch::factory()->create(['name' => 'php']);
    $branch->members()->create(['user_id' => $maintainer->id, 'role' => BranchRole::Maintainer]);

    $this->actingAs($maintainer)->post(route('branch-member.store', 'php'), [
        'members' => ['nuno'],
    ])->assertRedirect();

    expect($branch->members()->where('user_id', $friend->id)->exists())->toBeTrue()
        ->and($friend->notifications()->where('type', 'branch_member_added')->count())->toBe(1);

    $this->post(route('branch-member.store', 'php'), [
        'members' => ['stranger'],
    ])->assertSessionHasErrors('members.0');
});
