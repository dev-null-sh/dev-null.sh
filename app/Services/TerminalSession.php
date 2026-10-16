<?php

declare(strict_types=1);

namespace App\Services;

use Closure;

/**
 * The terminal app served over SSH: draws a screen for the connected user and reacts to their keys.
 */
final class TerminalSession
{
    private const string EnterScreen = "\e[?1049h\e[?25l";

    private const string LeaveScreen = "\e[?25h\e[?1049l";

    /**
     * q, Ctrl+C and Ctrl+D, pressed on their own: not as part of pasted text or an escape sequence like F2's "\eOQ".
     */
    private const array QuitKeys = ['q', 'Q', "\x03", "\x04"];

    private int $columns;

    private int $rows;

    private bool $ended = false;

    /**
     * @param  Closure(string): void  $write
     * @param  Closure(): void  $end
     */
    public function __construct(
        private readonly ?string $username,
        int $columns,
        int $rows,
        private readonly Closure $write,
        private readonly Closure $end,
    ) {
        $this->setSize($columns, $rows);
    }

    public function start(): void
    {
        ($this->write)(self::EnterScreen);

        $this->draw();
    }

    public function resize(int $columns, int $rows): void
    {
        $this->setSize($columns, $rows);

        $this->draw();
    }

    public function input(string $data): void
    {
        if (in_array($data, self::QuitKeys, true)) {
            $this->quit();
        }
    }

    /**
     * Terminals that don't report their size are assumed to be 80x24.
     */
    private function setSize(int $columns, int $rows): void
    {
        $this->columns = $columns > 0 ? $columns : 80;
        $this->rows = $rows > 0 ? $rows : 24;
    }

    private function quit(): void
    {
        if ($this->ended) {
            return;
        }

        $this->ended = true;

        ($this->write)(self::LeaveScreen);
        ($this->end)();
    }

    private function draw(): void
    {
        if ($this->ended) {
            return;
        }

        $lines = [
            'devnull.sh',
            '',
            $this->username === null ? 'Hello, stranger.' : "Hello, @{$this->username}.",
            "Your terminal is {$this->columns}x{$this->rows}.",
            '',
            'Press q to quit.',
        ];

        $top = max(0, intdiv($this->rows - count($lines), 2));

        $screen = "\e[2J";

        foreach ($lines as $index => $line) {
            $left = max(0, intdiv($this->columns - mb_strwidth($line), 2));

            $screen .= sprintf("\e[%d;%dH%s", $top + $index + 1, $left + 1, $index === 0 ? "\e[1m{$line}\e[0m" : $line);
        }

        ($this->write)($screen);
    }
}
