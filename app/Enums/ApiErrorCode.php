<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * The code that comes with an error from the signed API, next to its message, so the mobile app can tell errors apart
 * without reading the message. Once released, a code never changes.
 */
enum ApiErrorCode: string
{
    case InvalidSignature = 'invalid_signature';
    case DeviceNotApproved = 'device_not_approved';
    case InvalidGrant = 'invalid_grant';
    case UserBlocked = 'user_blocked';
    case UserUnavailable = 'user_unavailable';
    case OwnCommit = 'own_commit';
    case CommitUnavailable = 'commit_unavailable';
    case PasteisExhausted = 'pasteis_exhausted';
    case RateLimited = 'rate_limited';
    case GitHubSyncFailed = 'github_sync_failed';
    case TimedOut = 'timed_out';
    case Banned = 'banned';
    case CommitRulesRequired = 'commit_rules_required';
    case GitHubIssueRefused = 'github_issue_refused';
    case GitHubIssueUnanswered = 'github_issue_unanswered';

    public function status(): int
    {
        return match ($this) {
            self::InvalidGrant => 400,
            self::InvalidSignature, self::DeviceNotApproved => 401,
            self::UserBlocked, self::UserUnavailable, self::OwnCommit, self::TimedOut, self::Banned, self::CommitRulesRequired => 403,
            self::CommitUnavailable => 404,
            self::PasteisExhausted => 422,
            self::RateLimited => 429,
            self::GitHubIssueRefused, self::GitHubIssueUnanswered => 502,
            self::GitHubSyncFailed => 503,
        };
    }

    /**
     * What the code means to someone building on the API, as the API docs list it.
     */
    public function meaning(): string
    {
        return match ($this) {
            self::InvalidSignature => 'The signature is missing, stale, already used, or does not match the request.',
            self::DeviceNotApproved => 'The signature is right, but its key is not an approved device, or not any more.',
            self::InvalidGrant => 'The sign-in code is unknown, expired or already used, was issued for another key, does not match the verifier, or its account was deleted since.',
            self::UserBlocked => 'You blocked this person. The answer also has their profile, as user.',
            self::UserUnavailable => 'This profile is not available to you.',
            self::OwnCommit => 'You cannot report your own commit.',
            self::CommitUnavailable => 'The commit you report does not exist, was deleted, is not published, or is not yours to see. Elsewhere, such a commit is a plain 404.',
            self::PasteisExhausted => 'No pastéis left today.',
            self::RateLimited => 'Too many requests. Wait the seconds Retry-After says.',
            self::GitHubSyncFailed => 'GitHub did not answer. Try again in a minute.',
            self::GitHubIssueRefused => 'GitHub did not create the issue, so trying again is safe.',
            self::GitHubIssueUnanswered => 'GitHub did not answer, so the issue may exist: check the upstream issues before trying again.',
            self::TimedOut => 'Moderators paused posting for this account for a while.',
            self::Banned => 'The account is banned. All it can do is delete itself.',
            self::CommitRulesRequired => 'The commit rules have to be accepted once, in the web composer.',
        };
    }
}
