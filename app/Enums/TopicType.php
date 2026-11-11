<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * What a commit topic is, stored in `commit_topics.topic_type` next to its id: a GitHub organization, or a developer
 * who wasn't on /dev/null yet when the commit mentioned them. Either way, the id is their profile's.
 */
enum TopicType: string
{
    case Organization = 'organization';
    case Developer = 'developer';
}
