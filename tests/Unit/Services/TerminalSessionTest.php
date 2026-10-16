<?php

declare(strict_types=1);

use App\Services\TerminalSession;

/**
 * @return array{TerminalSession, Closure(): string, Closure(): int}
 */
function terminalSession(?string $username = null, int $columns = 80, int $rows = 24): array
{
    $output = '';
    $ended = 0;

    $session = new TerminalSession(
        $username,
        $columns,
        $rows,
        function (string $data) use (&$output): void {
            $output .= $data;
        },
        function () use (&$ended): void {
            $ended++;
        },
    );

    return [$session, function () use (&$output): string {
        [$written, $output] = [$output, ''];

        return $written;
    }, function () use (&$ended): int {
        return $ended;
    }];
}

it('greets the user in the alternate screen', function (): void {
    [$session, $output] = terminalSession('taylor');

    $session->start();

    expect($output())
        ->toStartWith("\e[?1049h\e[?25l\e[2J")
        ->toContain("\e[1mdevnull.sh\e[0m")
        ->toContain("\e[12;33HHello, @taylor.")
        ->toContain('Your terminal is 80x24.');
});

it('greets a stranger', function (): void {
    [$session, $output] = terminalSession();

    $session->start();

    expect($output())->toContain('Hello, stranger.');
});

it('redraws the screen when the terminal is resized', function (): void {
    [$session, $output] = terminalSession();

    $session->start();
    $output();
    $session->resize(120, 40);

    expect($output())
        ->toStartWith("\e[2J")
        ->toContain('Your terminal is 120x40.');
});

it('assumes 80x24 when the terminal reports no size', function (): void {
    [$session, $output] = terminalSession(columns: 0, rows: 0);

    $session->start();
    $session->resize(0, 0);

    expect(mb_substr_count($output(), 'Your terminal is 80x24.'))->toBe(2);
});

it('does not draw outside a tiny terminal', function (): void {
    [$session, $output] = terminalSession(columns: 4, rows: 2);

    $session->start();

    expect($output())->toContain("\e[1;1H\e[1mdevnull.sh");
});

it('quits on q, ctrl+c, and ctrl+d', function (string $key): void {
    [$session, $output, $ended] = terminalSession();

    $session->start();
    $output();
    $session->input($key);

    expect($output())->toBe("\e[?25h\e[?1049l")
        ->and($ended())->toBe(1);
})->with([
    'q' => 'q',
    'Q' => 'Q',
    'ctrl+c' => "\x03",
    'ctrl+d' => "\x04",
]);

it('ignores other keys, escape sequences, and pasted text', function (string $data): void {
    [$session, $output, $ended] = terminalSession();

    $session->start();
    $output();
    $session->input($data);

    expect($output())->toBe('')
        ->and($ended())->toBe(0);
})->with([
    'other key' => 'x',
    'arrow key' => "\e[A",
    'F2' => "\eOQ",
    'pasted text' => 'quick question',
]);

it('quits only once and stops drawing', function (): void {
    [$session, $output, $ended] = terminalSession();

    $session->start();
    $session->input('q');
    $output();
    $session->input('q');
    $session->resize(100, 30);

    expect($output())->toBe('')
        ->and($ended())->toBe(1);
});
