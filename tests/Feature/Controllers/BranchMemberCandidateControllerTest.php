<?php

declare(strict_types=1);

use App\Enums\BranchRole;
use App\Models\Branch;
use App\Models\User;

it('lists follow-graph people for a branch invite, with reasons', function (): void {
    $maintainer = User::factory()->create();
    $follower = User::factory()->create(['username' => 'ana_follows']);
    $follower->following()->attach($maintainer);
    $following = User::factory()->create(['username' => 'ana_following']);
    $maintainer->following()->attach($following);
    $stranger = User::factory()->create(['username' => 'ana_stranger']);
    $member = User::factory()->create(['username' => 'ana_member']);
    $maintainer->following()->attach($member);

    $branch = Branch::factory()->create(['name' => 'php']);
    $branch->members()->create(['user_id' => $maintainer->id, 'role' => BranchRole::Maintainer]);
    $branch->members()->create(['user_id' => $member->id, 'role' => BranchRole::Contributor]);

    $reasons = collect($this->actingAs($maintainer)->getJson(route('branch-member-candidate.index', ['branch' => 'php', 'q' => 'ana']))->assertOk()->json('candidates'))
        ->mapWithKeys(fn (array $candidate): array => [$candidate['user']['username'] => $candidate['reason']]);

    expect($reasons->all())->toEqualCanonicalizing([
        'ana_follows' => null,
        'ana_following' => null,
        'ana_member' => 'already on the branch',
        'ana_stranger' => 'not in your follow graph',
    ]);
});

it('lists the follow graph when the search is empty', function (): void {
    $maintainer = User::factory()->create();
    $friend = User::factory()->create(['username' => 'nuno']);
    $maintainer->following()->attach($friend);

    $branch = Branch::factory()->create(['name' => 'php']);
    $branch->members()->create(['user_id' => $maintainer->id, 'role' => BranchRole::Maintainer]);

    $usernames = collect($this->actingAs($maintainer)->getJson(route('branch-member-candidate.index', 'php'))->assertOk()->json('candidates'))
        ->pluck('user.username')
        ->all();

    expect($usernames)->toBe(['nuno']);
});

it('forbids contributors from listing invite candidates', function (): void {
    $maintainer = User::factory()->create();
    $contributor = User::factory()->create();

    $branch = Branch::factory()->create(['name' => 'php']);
    $branch->members()->create(['user_id' => $maintainer->id, 'role' => BranchRole::Maintainer]);
    $branch->members()->create(['user_id' => $contributor->id, 'role' => BranchRole::Contributor]);

    $this->actingAs($contributor)->getJson(route('branch-member-candidate.index', 'php'))
        ->assertForbidden();
});
