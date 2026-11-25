<?php

declare(strict_types=1);

namespace App\Services;

use Tempest\Highlight\Highlighter;

/**
 * How each client of the API signs a request, in the shell, Laravel and Node.js, highlighted as code blocks are.
 */
final readonly class ApiExamples
{
    public const string BaseUrl = 'https://dev-null.sh/api/v1';

    public function __construct(private Highlighter $highlighter)
    {
        //
    }

    /**
     * How each client signs a request, once, for Authentication: what every signed example calls.
     *
     * @return list<array{label: string, language: string, code: string, html: string}>
     */
    public function signing(): array
    {
        return $this->highlight([
            ['label' => 'curl', 'language' => 'bash', 'code' => <<<'SH'
                # devnull_sign METHOD PATH [BODY] sets $ts, $nonce and $signature
                devnull_sign() {
                  ts=$(date +%s)
                  nonce=$(openssl rand -hex 16)
                  signature=$(printf '%s\n%s\n%s\n%s\n%s' "$ts" "$nonce" "$1" "$2" "${3-}" \
                    | ssh-keygen -Y sign -n devnull-api -f ~/.ssh/devnull -q \
                    | sed '1d;$d' | tr -d '\n')
                }
                SH],
            ['label' => 'Laravel', 'language' => 'php', 'code' => <<<'PHP'
                use Illuminate\Support\Facades\Process;
                use Illuminate\Support\Str;

                /**
                 * The headers that sign a request to the /dev/null API.
                 *
                 * @return array<string, string>
                 */
                function devnullHeaders(string $method, string $path, string $body = ''): array
                {
                    $timestamp = (string) time();
                    $nonce = bin2hex(random_bytes(16));
                    $signature = Str::of(Process::input(implode("\n", [$timestamp, $nonce, $method, $path, $body]))
                        ->run(['ssh-keygen', '-Y', 'sign', '-n', 'devnull-api', '-f', getenv('HOME').'/.ssh/devnull', '-q'])
                        ->throw()
                        ->output())
                        ->replaceMatches('/-----[A-Z ]+-----|\s/', '')
                        ->value();

                    return [
                        'X-Devnull-Timestamp' => $timestamp,
                        'X-Devnull-Nonce' => $nonce,
                        'X-SSH-Signature' => $signature,
                    ];
                }
                PHP],
            ['label' => 'fetch', 'language' => 'javascript', 'code' => <<<'JS'
                // devnull.mjs, for Node.js, with ssh-keygen on the PATH
                import { execFileSync } from 'node:child_process';
                import { randomBytes } from 'node:crypto';
                import { homedir } from 'node:os';

                // The headers that sign a request to the /dev/null API.
                export function devnullHeaders(method, path, body = '') {
                  const timestamp = String(Math.floor(Date.now() / 1000));
                  const nonce = randomBytes(16).toString('hex');
                  const signature = execFileSync(
                    'ssh-keygen',
                    ['-Y', 'sign', '-n', 'devnull-api', '-f', `${homedir()}/.ssh/devnull`, '-q'],
                    { input: [timestamp, nonce, method, path, body].join('\n') },
                  )
                    .toString()
                    .replace(/-----[A-Z ]+-----|\s/g, '');

                  return {
                    'X-Devnull-Timestamp': timestamp,
                    'X-Devnull-Nonce': nonce,
                    'X-SSH-Signature': signature,
                  };
                }
                JS],
        ]);
    }

    /**
     * @param  list<array{label: string, language: string, code: string}>  $examples
     * @return list<array{label: string, language: string, code: string, html: string}>
     */
    private function highlight(array $examples): array
    {
        return array_map(fn (array $example): array => [
            ...$example,
            'html' => $this->highlighter->parse($example['code'], $example['language']),
        ], $examples);
    }
}
