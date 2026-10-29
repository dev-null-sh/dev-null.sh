<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * What a commit topic is, stored in `commit_topics.topic_type` next to its id. Only GitHub organizations so far, whose
 * id is their profile's.
 */
enum TopicType: string
{
    case Organization = 'organization';
}
