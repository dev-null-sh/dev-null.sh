<?php

declare(strict_types=1);

namespace App\Enums;

enum NotificationType: string
{
    case Followed = 'followed';
    case Replied = 'replied';
    case Mentioned = 'mentioned';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Quoted = 'quoted';
    case Starred = 'starred';
    case BranchActivity = 'branch_activity';
    case BranchMemberAdded = 'branch_member_added';
    case BranchRoleChanged = 'branch_role_changed';
    case BranchClaimApproved = 'branch_claim_approved';
    case BranchClaimRejected = 'branch_claim_rejected';
    case BranchOwnershipTransferred = 'branch_ownership_transferred';
}
