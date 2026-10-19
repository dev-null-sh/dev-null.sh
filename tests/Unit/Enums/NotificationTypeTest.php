<?php

declare(strict_types=1);

use App\Enums\NotificationType;

it('has the types of a notification', function (): void {
    expect(array_column(NotificationType::cases(), 'value', 'name'))->toBe([
        'Followed' => 'followed',
        'Replied' => 'replied',
        'Mentioned' => 'mentioned',
        'Approved' => 'approved',
        'Rejected' => 'rejected',
        'Quoted' => 'quoted',
        'Starred' => 'starred',
        'BranchActivity' => 'branch_activity',
        'BranchMemberAdded' => 'branch_member_added',
        'BranchRoleChanged' => 'branch_role_changed',
    ]);
});
