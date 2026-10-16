<?php

declare(strict_types=1);

namespace App\Enums;

enum GitHubReferenceType: string
{
    case Repository = 'repository';
    case Issue = 'issue';
    case PullRequest = 'pull_request';
    case Release = 'release';
    case Commit = 'commit';
    case Code = 'code';
}
