<?php

declare(strict_types=1);

namespace App\Enums;

enum MinesweeperGameStatus: string
{
    case Playing = 'playing';
    case Won = 'won';
    case Lost = 'lost';
}
