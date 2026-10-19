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
        'TagActivity' => 'tag_activity',
        'TagMemberAdded' => 'tag_member_added',
        'TagRoleChanged' => 'tag_role_changed',
    ]);
});
