<?php

declare(strict_types=1);

namespace App\Enums;

enum UnixRank: string
{
    case User = 'user';
    case Daemon = 'daemon';
    case Operator = 'operator';
    case Maintainer = 'maintainer';
    case Wheel = 'wheel';
    case Root = 'root';

    public static function forXp(int $xp): self
    {
        return match (true) {
            $xp >= self::Root->minXp() => self::Root,
            $xp >= self::Wheel->minXp() => self::Wheel,
            $xp >= self::Maintainer->minXp() => self::Maintainer,
            $xp >= self::Operator->minXp() => self::Operator,
            $xp >= self::Daemon->minXp() => self::Daemon,
            default => self::User,
        };
    }

    public static function forLevel(int $level): ?self
    {
        return array_find(self::cases(), fn (self $rank): bool => $rank->level() === $level);
    }

    public function level(): int
    {
        return match ($this) {
            self::User => 1,
            self::Daemon => 2,
            self::Operator => 3,
            self::Maintainer => 4,
            self::Wheel => 5,
            self::Root => 6,
        };
    }

    public function minXp(): int
    {
        return match ($this) {
            self::User => 0,
            self::Daemon => 50,
            self::Operator => 150,
            self::Maintainer => 400,
            self::Wheel => 1000,
            self::Root => 2500,
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::User => 'Unprivileged user (UID 1000)',
            self::Daemon => 'Background process running in user space',
            self::Operator => 'System operator with elevated permissions',
            self::Maintainer => 'Codebase curator managing repository state',
            self::Wheel => 'Member of the trusted wheel administrative group',
            self::Root => 'Superuser with full kernel privileges (UID 0)',
        };
    }

    public function next(): ?self
    {
        return match ($this) {
            self::User => self::Daemon,
            self::Daemon => self::Operator,
            self::Operator => self::Maintainer,
            self::Maintainer => self::Wheel,
            self::Wheel => self::Root,
            self::Root => null,
        };
    }
}
