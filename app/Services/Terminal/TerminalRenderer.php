<?php

declare(strict_types=1);

namespace App\Services\Terminal;

use App\Models\Commit;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

final readonly class TerminalRenderer
{
    /**
     * RGB colors for the 6 customizable user lanes (matching OpenGraphCanvas LANES).
     *
     * @var array<int, array{0: int, 1: int, 2: int}>
     */
    public const array LANES = [
        0 => [217, 72, 15],   // Orange (#d9480f)
        1 => [47, 158, 68],   // Green (#2f9e44)
        2 => [156, 54, 181],  // Purple (#9c36b5)
        3 => [12, 133, 153],  // Cyan (#0c8599)
        4 => [194, 37, 92],   // Pink (#c2255c)
        5 => [183, 121, 31],  // Yellow (#b7791f)
    ];

    /**
     * Standard 16-color ANSI fallbacks for terminal clients without TrueColor support.
     *
     * @var array<int, string>
     */
    public const array LANES_16 = [
        0 => "\033[33m", // Yellow / Orange
        1 => "\033[32m", // Green
        2 => "\033[35m", // Magenta
        3 => "\033[36m", // Cyan
        4 => "\033[31m", // Red / Pink
        5 => "\033[33m", // Yellow
    ];

    /**
     * Determines if the request is from a terminal/CLI client.
     */
    public static function wantsCli(Request $request): bool
    {
        if ($request->hasHeader('X-Inertia') || $request->wantsJson()) {
            return false;
        }

        if ($request->boolean('cli') || in_array($request->query('format'), ['cli', 'text'], true)) {
            return true;
        }

        $userAgent = (string) $request->userAgent();

        if (
            str_starts_with($userAgent, 'curl/')
            || str_starts_with($userAgent, 'Wget/')
            || str_starts_with($userAgent, 'HTTPie/')
        ) {
            return true;
        }

        return $request->accepts('text/plain') && ! $request->accepts('text/html');
    }

    /**
     * Determines whether ANSI colors should be emitted, respecting the NO_COLOR standard.
     */
    public static function isColorSupported(Request $request): bool
    {
        return ! ($request->hasHeader('No-Color') || $request->has('no-color') || $request->query('color') === '0');
    }

    /**
     * Determines whether standard 16-color ANSI should be used instead of 24-bit TrueColor.
     */
    public static function is16Color(Request $request): bool
    {
        return $request->query('color') === '16'
            || $request->header('X-Color-Depth') === '16'
            || $request->header('X-Color-Depth') === '4';
    }

    /**
     * Sanitizes user-controlled text to prevent terminal display manipulation / escape sequence injection.
     */
    public static function sanitize(string $text): string
    {
        // Strip ANSI escape sequences: ESC [ ... or ESC ] ... or ESC
        $text = (string) preg_replace('#\x1B(?:[@-Z\\-_]|\[[0-?]*[ -/]*[@-~])#', '', $text);

        // Strip C0 control characters except newline (\n) and tab (\t)
        return (string) preg_replace('/[\x00-\x08\x0B-\x1F\x7F]/u', '', $text);
    }

    /**
     * Renders the ASCII art header banner.
     */
    public function banner(bool $color = true): string
    {
        $cyan = $color ? "\033[38;2;12;133;153m" : '';
        $dim = $color ? "\033[90m" : '';
        $reset = $color ? "\033[0m" : '';

        $lines = [
            "{$cyan}   _                     _ _           _     {$reset}",
            "{$cyan}__| | _____   __  _ __  | | |  ___ ___| |__  {$reset}",
            "{$cyan}/ _` |/ _ \ \ / / | '_ \ | | | / __/ __| '_ \ {$reset}",
            "{$cyan}| (_| |  __/\ V /  | | | || | |_\__ \__ \ | | |{$reset}",
            "{$cyan}\__,_|\___| \_/   |_| |_| \__(_)___/___/_| |_|{$reset}",
            '',
            "{$dim}A social network for developers. Pipe your thoughts to /dev/null.{$reset}",
            '',
        ];

        return implode("\n", $lines);
    }

    /**
     * Renders the terminal landing page for unauthenticated visitors (matching web's welcome page).
     */
    public function landing(bool $color = true): string
    {
        $bold = $color ? "\033[1m" : '';
        $dim = $color ? "\033[90m" : '';
        $reset = $color ? "\033[0m" : '';
        $baseUrl = url('/');

        return implode("\n", [
            $this->banner($color),
            "  {$bold}A social network for developers.{$reset}",
            '',
            '  Sign in with GitHub, follow people who write code, and commit',
            '  plain text to a shared log, 280 characters at a time.',
            '',
            "  {$dim}".str_repeat('─', 60)."{$reset}",
            "  {$dim}Sign in:{$reset} {$baseUrl}/login",
            "  {$dim}Commit: {$reset} curl -fsSL {$baseUrl}/commit | bash -s -- 'Your message'",
            '',
        ]);
    }

    /**
     * Renders a list of commits as an ASCII/ANSI Git graph log.
     *
     * @param  iterable<int, Commit>  $commits
     */
    public function feed(iterable $commits, bool $color = true, bool $force16 = false, ?string $title = null): string
    {
        $output = [$this->banner($color)];

        if ($title !== null) {
            $dim = $color ? "\033[90m" : '';
            $bold = $color ? "\033[1m" : '';
            $reset = $color ? "\033[0m" : '';

            $output[] = "{$bold}{$title}{$reset}";
            $output[] = "{$dim}".str_repeat('─', 60)."{$reset}";
            $output[] = '';
        }

        $commitsList = is_array($commits) ? $commits : iterator_to_array($commits);
        $total = count($commitsList);

        if ($total === 0) {
            $dim = $color ? "\033[90m" : '';
            $reset = $color ? "\033[0m" : '';
            $output[] = "  {$dim}Nobody has committed anything yet. Be the first.{$reset}";
            $output[] = '';
        } else {
            foreach ($commitsList as $index => $commit) {
                $isLast = $index === $total - 1;
                $output[] = $this->commitCard($commit, $color, $force16, ! $isLast);
            }
        }

        $output[] = $this->footer($color);

        return implode("\n", $output)."\n";
    }

    /**
     * Renders a user's terminal profile card and recent commits.
     *
     * @param  iterable<int, Commit>  $commits
     */
    public function profile(User $user, iterable $commits, bool $color = true, bool $force16 = false): string
    {
        $lane = $this->laneColor($user->lane(), $color, $force16);
        $bold = $color ? "\033[1m" : '';
        $dim = $color ? "\033[90m" : '';
        $reset = $color ? "\033[0m" : '';

        $name = $user->name ? ' ('.self::sanitize($user->name).')' : '';
        $username = self::sanitize($user->username);
        $bio = $user->bio ? "\n  {$dim}".self::sanitize($user->bio)."{$reset}" : '';

        $user->loadPublicCounts();

        $stats = "{$user->commits_count} commits · {$user->followers_count} followers";

        $header = [
            $this->banner($color),
            "  {$lane}@{$username}{$reset}{$bold}{$name}{$reset}",
            "  {$dim}{$stats}{$reset}{$bio}",
            "  {$dim}".str_repeat('─', 60)."{$reset}",
            '',
        ];

        $commitsList = is_array($commits) ? $commits : iterator_to_array($commits);
        $total = count($commitsList);

        if ($total === 0) {
            $header[] = "  {$dim}@{$username} has not committed anything yet.{$reset}";
            $header[] = '';
        } else {
            foreach ($commitsList as $index => $commit) {
                $isLast = $index === $total - 1;
                $header[] = $this->commitCard($commit, $color, $force16, ! $isLast);
            }
        }

        $header[] = $this->footer($color);

        return implode("\n", $header)."\n";
    }

    /**
     * Renders the public profile preview for guests, prompting for GitHub sign-in to read commits.
     */
    public function profilePreview(User $user, bool $color = true, bool $force16 = false): string
    {
        $lane = $this->laneColor($user->lane(), $color, $force16);
        $bold = $color ? "\033[1m" : '';
        $dim = $color ? "\033[90m" : '';
        $reset = $color ? "\033[0m" : '';
        $baseUrl = url('/');

        $user->loadPublicCounts();

        $name = $user->name ? ' ('.self::sanitize($user->name).')' : '';
        $username = self::sanitize($user->username);
        $bio = $user->bio ? "\n  {$dim}".self::sanitize($user->bio)."{$reset}" : '';
        $stats = "{$user->commits_count} commits · {$user->followers_count} followers";

        return implode("\n", [
            $this->banner($color),
            "  {$lane}@{$username}{$reset}{$bold}{$name}{$reset}",
            "  {$dim}{$stats}{$reset}{$bio}",
            "  {$dim}".str_repeat('─', 60)."{$reset}",
            '',
            "  {$dim}Sign in with GitHub to read @{$username}'s commits on /dev/null:{$reset}",
            "  {$bold}{$baseUrl}/login{$reset}",
            '',
            $this->footer($color),
        ])."\n";
    }

    /**
     * Renders a single commit with its ancestors and replies thread.
     *
     * @param  iterable<int, Commit>  $ancestors
     * @param  iterable<int, Commit>  $replies
     */
    public function thread(Commit $commit, iterable $ancestors, iterable $replies, bool $color = true, bool $force16 = false): string
    {
        $output = [$this->banner($color)];

        $ancestorList = is_array($ancestors) ? $ancestors : iterator_to_array($ancestors);
        $repliesList = is_array($replies) ? $replies : iterator_to_array($replies);

        // Ancestors...
        foreach ($ancestorList as $ancestor) {
            $output[] = $this->commitCard($ancestor, $color, $force16, true);
        }

        // Focused commit...
        $output[] = $this->commitCard($commit, $color, $force16, count($repliesList) > 0, isFocused: true);

        // Replies...
        $totalReplies = count($repliesList);
        foreach ($repliesList as $index => $reply) {
            $isLast = $index === $totalReplies - 1;
            $output[] = $this->commitCard($reply, $color, $force16, ! $isLast, isReply: true);
        }

        $output[] = $this->footer($color);

        return implode("\n", $output)."\n";
    }

    /**
     * Renders a public commit preview for guests.
     */
    public function commitPreview(Commit $commit, bool $color = true, bool $force16 = false): string
    {
        $dim = $color ? "\033[90m" : '';
        $bold = $color ? "\033[1m" : '';
        $reset = $color ? "\033[0m" : '';
        $baseUrl = url('/');

        return implode("\n", [
            $this->banner($color),
            $this->commitCard($commit, $color, $force16, false),
            "  {$dim}Sign in with GitHub to join the conversation and read replies:{$reset}",
            "  {$bold}{$baseUrl}/login{$reset}",
            '',
            $this->footer($color),
        ])."\n";
    }

    /**
     * Renders an individual commit node with ASCII rail.
     */
    public function commitCard(Commit $commit, bool $color = true, bool $force16 = false, bool $hasFollowing = true, bool $isFocused = false, bool $isReply = false): string
    {
        $lane = $this->laneColor($commit->user->lane(), $color, $force16);
        $bold = $color ? "\033[1m" : '';
        $dim = $color ? "\033[90m" : '';
        $reset = $color ? "\033[0m" : '';

        $hash = Str::substr($commit->id, 0, 7);
        $time = $commit->created_at->diffForHumans(short: true);
        $stars = $commit->stars_count > 0 ? " · {$commit->stars_count} ★" : '';
        $replies = $commit->replies_count > 0 ? " · {$commit->replies_count} ⤿" : '';

        $railStem = $hasFollowing ? "{$dim}│{$reset}" : ' ';
        $nodeSymbol = $isReply ? '├─' : '*';

        if ($isFocused) {
            $hashText = "{$bold}{$hash}{$reset} {$dim}(HEAD){$reset}";
        } else {
            $hashText = "{$bold}{$hash}{$reset}";
        }

        $username = self::sanitize($commit->user->username);
        $headerLine = "  {$lane}{$nodeSymbol}{$reset}   {$hashText} {$lane}@{$username}{$reset} {$dim}· {$time}{$stars}{$replies}{$reset}";

        $body = self::sanitize($commit->body);
        $bodyLines = explode("\n", wordwrap($body, 70, "\n", true));
        $renderedBody = [];

        foreach ($bodyLines as $line) {
            $renderedBody[] = "  {$railStem}   {$line}";
        }

        if ($hasFollowing) {
            $renderedBody[] = "  {$railStem}";
        } else {
            $renderedBody[] = '';
        }

        return $headerLine."\n".implode("\n", $renderedBody);
    }

    /**
     * Renders the terminal quick-command footer.
     */
    public function footer(bool $color = true): string
    {
        $dim = $color ? "\033[90m" : '';
        $reset = $color ? "\033[0m" : '';
        $baseUrl = url('/');

        return implode("\n", [
            "  {$dim}".str_repeat('─', 60)."{$reset}",
            "  {$dim}Commit:{$reset} curl -fsSL {$baseUrl}/commit | bash -s -- 'Your message'",
            "  {$dim}Log:   {$reset} curl -s {$baseUrl}/log",
        ]);
    }

    /**
     * Resolves the ANSI escape sequence for a user lane (24-bit TrueColor or 16-color ANSI).
     */
    public function laneColor(int $laneIndex, bool $color, bool $force16 = false): string
    {
        if (! $color) {
            return '';
        }

        if ($force16) {
            return self::LANES_16[$laneIndex] ?? self::LANES_16[0];
        }

        $rgb = self::LANES[$laneIndex] ?? self::LANES[0];

        return sprintf("\033[38;2;%d;%d;%dm", $rgb[0], $rgb[1], $rgb[2]);
    }
}
