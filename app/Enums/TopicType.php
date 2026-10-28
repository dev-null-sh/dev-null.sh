<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * What a commit topic is, stored in `commit_topics.topic_type` next to its id. Only GitHub organizations so far, which
 * are profiles.
 */
enum TopicType: string
{
    case Profile = 'profile';
}
