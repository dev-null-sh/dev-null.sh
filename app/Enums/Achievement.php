<?php

declare(strict_types=1);

namespace App\Enums;

enum Achievement: string
{
    case Init = 'init';
    case Uptime = 'uptime';
    case Daemon = 'daemon';
    case Stdout = 'stdout';
    case KernelSpace = 'kernel_space';
    case Stargazer = 'stargazer';
    case Superstar = 'superstar';
    case Pipe = 'pipe';
    case MaxPayload = 'max_payload';
    case CliNative = 'cli_native';
    case Syntax = 'syntax';
    case Sysadmin = 'sysadmin';

    public function label(): string
    {
        return match ($this) {
            self::Init => 'init',
            self::Uptime => 'uptime',
            self::Daemon => 'daemon',
            self::Stdout => 'stdout',
            self::KernelSpace => 'kernel space',
            self::Stargazer => 'stargazer',
            self::Superstar => 'superstar',
            self::Pipe => 'pipe',
            self::MaxPayload => 'max payload',
            self::CliNative => 'cli native',
            self::Syntax => 'syntax',
            self::Sysadmin => 'sysadmin',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Init => 'Piped your first commit to /dev/null.',
            self::Uptime => 'Maintained a 7-day commit streak without a reboot.',
            self::Daemon => 'Maintained a 30-day commit streak.',
            self::Stdout => 'Reached 50 commits piped to the void.',
            self::KernelSpace => 'Reached 100 commits to /dev/null.',
            self::Stargazer => 'Starred 10 commits by other developers.',
            self::Superstar => 'Received 25 stars on your commits.',
            self::Pipe => 'Joined conversations with 5 replies.',
            self::MaxPayload => 'Committed an exact 280-character post.',
            self::CliNative => 'Committed from the terminal via SSH signature.',
            self::Syntax => 'Shared formatted code blocks in a commit.',
            self::Sysadmin => 'Maintained a 100-day commit streak.',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Init => 'terminal',
            self::Uptime => 'flame',
            self::Daemon => 'zap',
            self::Stdout => 'box',
            self::KernelSpace => 'shield',
            self::Stargazer => 'star',
            self::Superstar => 'sparkles',
            self::Pipe => 'git-merge',
            self::MaxPayload => 'maximize',
            self::CliNative => 'code',
            self::Syntax => 'file-code',
            self::Sysadmin => 'server',
        };
    }

    public function target(): int
    {
        return match ($this) {
            self::Init => 1,
            self::Uptime => 7,
            self::Daemon => 30,
            self::Stdout => 50,
            self::KernelSpace => 100,
            self::Stargazer => 10,
            self::Superstar => 25,
            self::Pipe => 5,
            self::MaxPayload => 1,
            self::CliNative => 1,
            self::Syntax => 1,
            self::Sysadmin => 100,
        };
    }
}
