<?php

declare(strict_types=1);

use App\Actions\ClearStaleBranchVerifications;
use App\Actions\CreateBranchMembers;
use App\Actions\DeleteUser;
use App\Contracts\Services\DnsResolver;
use App\Enums\BranchRole;
use App\Models\Branch;
use App\Models\Commit;
use App\Models\Conversation;
use App\Models\User;
use App\Services\BranchDnsVerification;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

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

it('requests ownership without granting membership until a moderator approves', function (): void {
    $owner = User::factory()->create(['username' => 'taylor']);
    $admin = User::factory()->create(['username' => 'admin']);
    $watcher = User::factory()->create();
    config(['app.admins' => ['taylor', 'admin']]);

    $this->actingAs($owner)->post(route('branch-claim.store', 'laravel'))
        ->assertRedirect(route('branch.show', 'laravel'));

    $branch = Branch::query()->where('name', 'laravel')->sole();
    expect($branch->isOwned())->toBeFalse()
        ->and($branch->hasPendingClaim())->toBeTrue()
        ->and($branch->claim_requested_by)->toBe($owner->id)
        ->and($branch->isMaintainedBy($owner))->toBeFalse();

    $this->actingAs($admin)->get(route('branch-claim-request.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('moderation/claims')
            ->where('claims.data.0.name', 'laravel')
            ->where('claims.data.0.requester.username', 'taylor'));

    $this->actingAs($admin)->post(route('branch-claim-approval.store', 'laravel'))
        ->assertRedirect();

    expect($branch->refresh()->isOwned())->toBeTrue()
        ->and($branch->hasPendingClaim())->toBeFalse()
        ->and($branch->isMaintainedBy($owner))->toBeTrue()
        ->and($owner->notifications()->where('type', 'branch_claim_approved')->count())->toBe(1);

    $this->actingAs($owner)->put(route('branch-domain.update', 'laravel'), [
        'domain' => 'laravel.com',
    ])->assertRedirect();

    $branch->refresh();
    $expected = resolve(BranchDnsVerification::class)->recordValue($branch);

    $this->app->instance(DnsResolver::class, new readonly class($expected) implements DnsResolver
    {
        public function __construct(private string $txt)
        {
            //
        }

        public function resolve(string $host): array
        {
            return ['93.184.216.34'];
        }

        public function txt(string $host): ?array
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
            ->where('verifications.data.0.id', $branch->id)
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

it('lets moderators reject an ownership claim', function (): void {
    $claimer = User::factory()->create();
    $admin = User::factory()->create(['username' => 'admin']);
    config(['app.admins' => ['admin']]);

    $this->actingAs($claimer)->post(route('branch-claim.store', 'php'))
        ->assertRedirect(route('branch.show', 'php'));

    $this->actingAs($admin)->post(route('branch-claim-rejection.store', 'php'))
        ->assertRedirect();

    $branch = Branch::query()->where('name', 'php')->sole();

    expect($branch->hasPendingClaim())->toBeFalse()
        ->and($branch->isOwned())->toBeFalse()
        ->and($claimer->notifications()->where('type', 'branch_claim_rejected')->count())->toBe(1);
});

it('lets the requester cancel a pending ownership claim', function (): void {
    $claimer = User::factory()->create();

    $this->actingAs($claimer)->post(route('branch-claim.store', 'casual'))
        ->assertRedirect(route('branch.show', 'casual'));

    $this->actingAs($claimer)->delete(route('branch-claim.destroy', 'casual'))
        ->assertRedirect(route('branch.show', 'casual'));

    expect(Branch::query()->where('name', 'casual')->sole()->hasPendingClaim())->toBeFalse();
});

it('lets maintainers transfer ownership to another member', function (): void {
    $maintainer = User::factory()->create();
    $friend = User::factory()->create(['username' => 'nuno']);
    $maintainer->following()->attach($friend);

    $branch = Branch::factory()->create(['name' => 'php']);
    $branch->members()->create(['user_id' => $maintainer->id, 'role' => BranchRole::Maintainer]);
    $branch->members()->create(['user_id' => $friend->id, 'role' => BranchRole::Contributor]);

    $this->actingAs($maintainer)->post(route('branch-transfer.store', 'php'), [
        'username' => 'nuno',
    ])->assertRedirect();

    expect($branch->refresh()->isMaintainedBy($friend))->toBeTrue()
        ->and($branch->roleOf($maintainer))->toBe(BranchRole::Contributor)
        ->and($friend->notifications()->where('type', 'branch_ownership_transferred')->count())->toBe(1);
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

        public function txt(string $host): ?array
        {
            return [];
        }
    });

    expect(resolve(ClearStaleBranchVerifications::class)->handle())->toBe(1)
        ->and($branch->refresh()->isVerified())->toBeFalse();
});

it('keeps verification when the TXT lookup fails', function (): void {
    $branch = Branch::factory()->verified('laravel.com')->create(['name' => 'laravel']);

    $this->app->instance(DnsResolver::class, new readonly class implements DnsResolver
    {
        public function resolve(string $host): array
        {
            return [];
        }

        public function txt(string $host): ?array
        {
            return null;
        }
    });

    expect(resolve(ClearStaleBranchVerifications::class)->handle())->toBe(0)
        ->and($branch->refresh()->isVerified())->toBeTrue();
});

it('lets only matching Remotes org maintainers claim and verify a branch', function (): void {
    $orgMaintainer = User::factory()->create();
    $orgContributor = User::factory()->create();
    $outsider = User::factory()->create();
    $admin = User::factory()->create(['username' => 'admin']);
    config(['app.admins' => ['admin']]);
    $orgContributor->following()->attach($orgMaintainer);

    createOrg($orgMaintainer, [$orgContributor], name: 'laravel');

    $this->actingAs($outsider)->post(route('branch-claim.store', 'laravel'))
        ->assertSessionHasErrors('branch');

    $this->actingAs($orgContributor)->post(route('branch-claim.store', 'laravel'))
        ->assertSessionHasErrors('branch');

    $this->actingAs($orgMaintainer)->post(route('branch-claim.store', 'laravel'))
        ->assertRedirect(route('branch.show', 'laravel'));

    $this->actingAs($admin)->post(route('branch-claim-approval.store', 'laravel'))
        ->assertRedirect();

    $branch = Branch::query()->where('name', 'laravel')->sole();
    expect($branch->isMaintainedBy($orgMaintainer))->toBeTrue();

    $this->actingAs($orgMaintainer)->put(route('branch-domain.update', 'laravel'), [
        'domain' => 'laravel.com',
    ])->assertRedirect();

    $branch->refresh();
    $expected = resolve(BranchDnsVerification::class)->recordValue($branch);

    $this->app->instance(DnsResolver::class, new readonly class($expected) implements DnsResolver
    {
        public function __construct(private string $txt)
        {
            //
        }

        public function resolve(string $host): array
        {
            return ['93.184.216.34'];
        }

        public function txt(string $host): ?array
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

    $this->actingAs($claimer)->post(route('branch-claim.store', 'laravel'))
        ->assertRedirect(route('branch.show', 'laravel'));
});

it('blocks settings and edits after someone is removed from the branch', function (): void {
    $maintainer = User::factory()->create();
    $contributor = User::factory()->create(['username' => 'admin']);
    config(['app.admins' => ['admin']]);

    $branch = Branch::factory()->create(['name' => 'php', 'description' => 'old']);
    $branch->members()->create(['user_id' => $maintainer->id, 'role' => BranchRole::Maintainer]);
    $branch->members()->create(['user_id' => $contributor->id, 'role' => BranchRole::Contributor]);

    $this->actingAs($maintainer)->delete(route('branch-member.destroy', ['php', $contributor->username]))
        ->assertRedirect();

    expect($branch->members()->where('user_id', $contributor->id)->exists())->toBeFalse();

    $this->actingAs($contributor)->get(route('branch.settings', 'php'))
        ->assertForbidden();

    $this->actingAs($contributor)->put(route('branch-description.update', 'php'), [
        'description' => 'should not stick',
    ])->assertForbidden();

    expect($branch->refresh()->description)->toBe('old');
});

it('lets contributors edit the description but not manage the branch', function (): void {
    $maintainer = User::factory()->create();
    $contributor = User::factory()->create();

    $branch = Branch::factory()->create(['name' => 'php', 'description' => 'old']);
    $branch->members()->create(['user_id' => $maintainer->id, 'role' => BranchRole::Maintainer]);
    $branch->members()->create(['user_id' => $contributor->id, 'role' => BranchRole::Contributor]);

    $this->actingAs($contributor)->get(route('branch.settings', 'php'))
        ->assertOk();

    $this->actingAs($contributor)->put(route('branch-description.update', 'php'), [
        'description' => 'new copy',
    ])->assertRedirect();

    expect($branch->refresh()->description)->toBe('new copy');

    $this->actingAs($contributor)->put(route('branch-domain.update', 'php'), [
        'domain' => 'example.com',
    ])->assertForbidden();

    $this->actingAs($contributor)->post(route('branch-member.store', 'php'), [
        'members' => [$maintainer->username],
    ])->assertForbidden();
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

it('lets a member remove themselves by username without scoping to branch_members', function (): void {
    $maintainer = User::factory()->create(['username' => 'jjaybrown']);
    $friend = User::factory()->create(['username' => 'nuno']);
    $maintainer->following()->attach($friend);

    $branch = Branch::factory()->create(['name' => 'php']);
    $branch->members()->create(['user_id' => $maintainer->id, 'role' => BranchRole::Maintainer]);
    $branch->members()->create(['user_id' => $friend->id, 'role' => BranchRole::Contributor]);

    $this->actingAs($friend)->delete(route('branch-member.destroy', ['php', 'nuno']))
        ->assertRedirect(route('branch.show', 'php'));

    expect($branch->members()->where('user_id', $friend->id)->exists())->toBeFalse()
        ->and($branch->isMaintainedBy($maintainer))->toBeTrue();
});

it('refuses verification before the branch is owned', function (): void {
    $user = User::factory()->create();
    $branch = Branch::factory()->create([
        'name' => 'orphan',
        'domain' => 'example.com',
        'verification_token' => 'token',
    ]);

    $this->actingAs($user)->post(route('branch-verification.store', 'orphan'))
        ->assertForbidden();

    expect($branch->refresh()->hasPendingVerification())->toBeFalse();
});

it('treats the oldest matching Remotes org as the sole claim authority', function (): void {
    $olderMaintainer = User::factory()->create();
    $olderMember = User::factory()->create();
    $newerMaintainer = User::factory()->create();
    $newerMember = User::factory()->create();
    $olderMember->following()->attach($olderMaintainer);
    $newerMember->following()->attach($newerMaintainer);

    createOrg($olderMaintainer, [$olderMember], name: 'laravel');
    $this->travel(1)->minute();
    createOrg($newerMaintainer, [$newerMember], name: 'laravel');

    expect(Conversation::userMayVerifyBranch($olderMaintainer, 'laravel'))->toBeTrue()
        ->and(Conversation::userMayVerifyBranch($newerMaintainer, 'laravel'))->toBeFalse();

    $this->actingAs($newerMaintainer)->post(route('branch-claim.store', 'laravel'))
        ->assertSessionHasErrors('branch');

    $this->actingAs($olderMaintainer)->post(route('branch-claim.store', 'laravel'))
        ->assertRedirect(route('branch.show', 'laravel'));
});

it('clears branding when the last member leaves', function (): void {
    $maintainer = User::factory()->create();
    $branch = Branch::factory()->verified('example.com')->create([
        'name' => 'php',
        'description' => 'PHP',
        'domain_description' => 'Homepage',
        'og_image_url' => 'https://example.com/og.png',
        'brand_color' => '#112233',
    ]);
    $branch->members()->create(['user_id' => $maintainer->id, 'role' => BranchRole::Maintainer]);

    $this->actingAs($maintainer)->delete(route('branch-member.destroy', ['php', $maintainer->username]))
        ->assertRedirect();

    $branch->refresh();

    expect($branch->isOwned())->toBeFalse()
        ->and($branch->domain)->toBeNull()
        ->and($branch->description)->toBeNull()
        ->and($branch->domain_description)->toBeNull()
        ->and($branch->og_image_url)->toBeNull()
        ->and($branch->brand_color)->toBeNull();
});

it('rechecks maintainer rights after locking when adding members', function (): void {
    $maintainer = User::factory()->create();
    $contributor = User::factory()->create();
    $friend = User::factory()->create();
    $maintainer->following()->attach($friend);

    $branch = Branch::factory()->create(['name' => 'php']);
    $branch->members()->create(['user_id' => $maintainer->id, 'role' => BranchRole::Maintainer]);
    $branch->members()->create(['user_id' => $contributor->id, 'role' => BranchRole::Contributor]);

    expect(fn () => resolve(CreateBranchMembers::class)->handle($branch, $contributor, [$friend]))
        ->toThrow(ValidationException::class);
});

it('hands ownership to a contributor when the sole maintainer deletes their account', function (): void {
    $maintainer = User::factory()->create();
    $contributor = User::factory()->create();

    $branch = Branch::factory()->verified('example.com')->create([
        'name' => 'php',
        'brand_color' => '#112233',
        'domain_description' => 'Homepage',
        'og_image_url' => 'https://example.com/og.png',
    ]);
    $branch->members()->create(['user_id' => $maintainer->id, 'role' => BranchRole::Maintainer]);
    $branch->members()->create(['user_id' => $contributor->id, 'role' => BranchRole::Contributor]);

    resolve(DeleteUser::class)->handle($maintainer);

    expect(User::query()->whereKey($maintainer->id)->exists())->toBeFalse()
        ->and($branch->refresh()->isMaintainedBy($contributor))->toBeTrue()
        ->and($branch->isVerified())->toBeTrue()
        ->and($branch->brand_color)->toBe('#112233');
});
