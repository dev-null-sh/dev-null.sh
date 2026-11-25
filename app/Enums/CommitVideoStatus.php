<?php

declare(strict_types=1);

namespace App\Enums;

enum CommitVideoStatus: string
{
    case Uploading = 'uploading';
    case Processing = 'processing';
    case Ready = 'ready';
    case Failed = 'failed';
}
