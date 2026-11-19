<?php

declare(strict_types=1);

namespace App\Enums;

enum ContributorHelp: string
{
    case Coding = 'coding';
    case Testing = 'testing';
    case Documentation = 'documentation';
    case Design = 'design';
    case Feedback = 'feedback';

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
