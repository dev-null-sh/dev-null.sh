<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * What a star says, picked by holding the star button. A star without one is a plain star.
 */
enum StarFlavor: string
{
    case Star = 'star';
    case Ship = 'ship';
    case Fire = 'fire';
    case Brain = 'brain';
    case Party = 'party';
    case Eyes = 'eyes';
}
