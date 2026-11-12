<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Str;
use stdClass;
use Tempest\Highlight\Highlighter;

/**
 * How to call an endpoint of the API docs with curl, Laravel's HTTP client and fetch, filled in with example values
 * and highlighted as code blocks are. A signed request sends the very bytes it signed, so its body is encoded once.
 */
final readonly class ApiExamples
{
    public const string BaseUrl = 'https://dev-null.sh/api/v1';

    /**
     * What a signature covers of the URL: the path from the root, then the query.
     */
    private const string SignedPrefix = '/api/v1';

    private const string Uuid = '0195d8c4-7b2e-7c4f-9e1a-5b6c7d8e9f01';

    /**
     * Stands for tomorrow in a body until each client writes how to compute it: a scheduled commit must be due within
     * the next days, which no date written in an example, cached until the code changes, stays.
     */
    private const string Tomorrow = "\u{E000}tomorrow\u{E000}";

    /**
     * Values for the path parameters the API names, and the fields whose meaning or format a placeholder would miss.
     */
    private const array Samples = [
        'scheduled_at' => self::Tomorrow,
        'timezone' => 'Europe/Paris',
        'url' => 'https://www.twitch.tv/nunomaduro',
        'sdp' => 'v=0…',
        'handle' => 'nunomaduro',
        'commit' => '01a1c63c-ae57-70cc-adf3-2298cc075627',
        'org' => self::Uuid,
        'scheduledCommit' => self::Uuid,
        'device' => self::Uuid,
        'notification' => self::Uuid,
        'headline' => self::Uuid,
        'message' => '1',
        'log' => 'following',
        'body' => 'Hello from the API!',
        'name' => 'The Artisans',
        'q' => 'laravel',
        'emoji' => '👍',
    ];

    /**
     * Query values that only make sense on one path, by path then name.
     */
    private const array QuerySamples = [
        '/search/issues' => ['q' => '857'],
    ];

    public function __construct(private Highlighter $highlighter)
    {
        //
    }

    /**
     * @param  array<string, mixed>  $endpoint
     * @return list<array{label: string, language: string, code: string, html: string}>
     */
    public function for(array $endpoint): array
    {
        $method = is_string($endpoint['method'] ?? null) ? $endpoint['method'] : 'GET';
        $path = $this->path(is_string($endpoint['path'] ?? null) ? $endpoint['path'] : '/', $this->list($endpoint['parameters'] ?? null));
        $fields = $this->list(is_array($endpoint['body'] ?? null) ? $endpoint['body']['fields'] ?? null : null);
        $body = $this->body($fields) ?? $this->body($fields, everyField: true);
        $signed = ($endpoint['signature'] ?? null) === 'required';
        $answers = ! in_array('204', array_column($this->list($endpoint['responses'] ?? null), 'status'), true);

        return $this->highlight([
            ['label' => 'curl', 'language' => 'bash', 'code' => $this->curl($method, $path, $body, $signed)],
            ['label' => 'Laravel', 'language' => 'php', 'code' => $this->laravel($method, $path, $body, $signed)],
            ['label' => 'fetch', 'language' => 'javascript', 'code' => $this->fetch($method, $path, $body, $signed, $answers)],
        ]);
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

    /**
     * A shell script: the body in a variable when there is one, so the signature covers the very bytes curl sends.
     *
     * @param  array<string, mixed>|null  $body
     */
    private function curl(string $method, string $path, ?array $body, bool $signed): string
    {
        $url = self::BaseUrl.$path;
        $lines = [($method === 'GET' ? 'curl ' : "curl -X {$method} ").(str_contains($url, '?') ? "'{$url}'" : $url), "-H 'Accept: application/json'"];

        if ($signed) {
            $lines = [...$lines, '-H "X-Devnull-Timestamp: $ts"', '-H "X-Devnull-Nonce: $nonce"', '-H "X-SSH-Signature: $signature"'];
        }

        $json = $body === null ? '' : str_replace(self::Tomorrow, '\'"$tomorrow"\'', $this->shell($this->json($body)));
        $tomorrow = str_contains($json, '$tomorrow') ? ['tomorrow=$(date -u -d tomorrow +%Y-%m-%dT%H:%M:%S+00:00 2>/dev/null || date -u -v+1d +%Y-%m-%dT%H:%M:%S+00:00)'] : [];

        if ($body !== null) {
            $lines = [...$lines, "-H 'Content-Type: application/json'", $signed ? '-d "$body"' : "-d {$json}"];
        }

        $request = implode(" \\\n  ", $lines);

        if (! $signed) {
            return implode("\n", [...$tomorrow, $request]);
        }

        return implode("\n", [
            ...$tomorrow,
            ...($body === null ? [] : ["body={$json}"]),
            "devnull_sign {$method} '".self::SignedPrefix.$path."'".($body === null ? '' : ' "$body"').' # see Authentication',
            '',
            $request,
        ]);
    }

    /**
     * Laravel's HTTP client, its headers from devnullHeaders(), over the body it then sends as it is. A write without a
     * body goes through send(): post() and put() given nothing send `[]`, which a signature over nothing doesn't cover.
     *
     * @param  array<string, mixed>|null  $body
     */
    private function laravel(string $method, string $path, ?array $body, bool $signed): string
    {
        $calls = ['->acceptJson()'];

        if ($signed) {
            $calls[] = "->withHeaders(devnullHeaders('{$method}', '".self::SignedPrefix.$path."'".($body === null ? '' : ', $body').')) // see Authentication';
        }

        if ($body !== null) {
            $calls[] = '->withBody($body)';
        }

        $url = "'".mb_ltrim($path, '/')."'";
        $request = $body === null && ! in_array($method, ['GET', 'DELETE'], true)
            ? "->send('{$method}', {$url})"
            : '->'.mb_strtolower($method)."({$url})";

        $calls = [...$calls, $request, '->throw()', '->json();'];

        return implode("\n", [
            'use Illuminate\Support\Facades\Http;',
            '',
            ...($body === null ? [] : ['$body = json_encode('.str_replace("'".self::Tomorrow."'", 'now()->addDay()->toIso8601String()', $this->php($body)).');', '']),
            "\$response = Http::baseUrl('".self::BaseUrl."')",
            ...array_map(fn (string $call): string => "    {$call}", $calls),
        ]);
    }

    /**
     * fetch, asking for JSON, its headers from devnullHeaders() when signing, which runs in Node.js, over the body it then
     * sends as it is. fetch doesn't throw on an error status, so the example does, as Laravel's throw() does.
     *
     * @param  array<string, mixed>|null  $body
     */
    private function fetch(string $method, string $path, ?array $body, bool $signed, bool $answers): string
    {
        $options = [];

        if ($method !== 'GET') {
            $options[] = "method: '{$method}',";
        }

        $signature = "devnullHeaders('{$method}', '".self::SignedPrefix.$path."'".($body === null ? '' : ', body').')';

        $options = [
            ...$options,
            'headers: {',
            ...($signed ? ["  ...{$signature},"] : []),
            "  Accept: 'application/json',",
            ...($body === null ? [] : ["  'Content-Type': 'application/json',"]),
            '},',
            ...($body === null ? [] : ['body,']),
        ];

        $call = "fetch('".self::BaseUrl.$path."', {\n".implode("\n", array_map(fn (string $option): string => "  {$option}", $options))."\n});";

        return implode("\n", [
            ...($signed ? ["import { devnullHeaders } from './devnull.mjs'; // see Authentication", ''] : []),
            ...($body === null ? [] : ['const body = JSON.stringify('.str_replace("'".self::Tomorrow."'", "new Date(Date.now() + 86400000).toISOString().replace(/\\.\\d{3}Z\$/, '+00:00')", $this->javascript($body)).');', '']),
            "const response = await {$call}",
            'if (!response.ok) throw new Error(await response.text());',
            ...($answers ? ['const json = await response.json();'] : []),
        ]);
    }

    /**
     * The path with its parameters filled in, and the query parameters a request can't go without.
     *
     * @param  list<mixed>  $parameters
     */
    private function path(string $path, array $parameters): string
    {
        $samples = self::QuerySamples[$path] ?? [];
        $path = (string) preg_replace_callback('/\{(\w+)\}/', fn (array $match): string => self::Samples[$match[1]] ?? $match[0], $path);

        $query = [];

        foreach ($parameters as $parameter) {
            if (is_array($parameter) && ($parameter['in'] ?? null) === 'query' && ($parameter['required'] ?? false) === true && is_string($parameter['name'] ?? null)) {
                $query[Str::chopEnd($parameter['name'], '[]')] = $samples[$parameter['name']] ?? $this->sample($parameter);
            }
        }

        return $query === [] ? $path : $path.'?'.http_build_query($query);
    }

    /**
     * A body with every required field, as an example would fill it, or with every field when asked: a body that
     * requires none still means something when it is sent empty, such as an org's name taken away. An object is filled
     * with its own required fields, and left out when it has none: an empty object is never what a request means to
     * send. A list of objects is a list of one.
     *
     * @param  list<mixed>  $fields
     * @return array<string, mixed>|null
     */
    private function body(array $fields, bool $everyField = false): ?array
    {
        $body = [];

        foreach ($fields as $field) {
            if (! is_array($field) || ! $everyField && ($field['required'] ?? false) !== true || ! is_string($field['name'] ?? null)) {
                continue;
            }

            $children = $this->list($field['children'] ?? null);
            $type = is_string($field['type'] ?? null) ? $field['type'] : '';

            if ($children === []) {
                $body[$field['name']] = $this->sample($field);
            } elseif (($object = $this->body($children)) !== null) {
                $body[$field['name']] = str_starts_with($type, 'array of ') ? [$object] : $object;
            }
        }

        return $body === [] ? null : $body;
    }

    /**
     * @param  array<array-key, mixed>  $field
     */
    private function sample(array $field): mixed
    {
        $name = is_string($field['name'] ?? null) ? $field['name'] : '';
        $type = is_string($field['type'] ?? null) ? Str::before($field['type'], ' | ') : 'string';
        $enum = $this->list($field['enum'] ?? null);

        return match (true) {
            $enum !== [] => $enum[0],
            isset(self::Samples[$name]) => self::Samples[$name],
            $type === 'uuid' => self::Uuid,
            $type === 'integer', $type === 'number' => 1,
            $type === 'boolean' => true,
            str_starts_with($type, 'array of ') => [$this->sample(['type' => mb_trim(Str::after($type, 'array of '), '()')])],
            $type === 'array' => [],
            $type === 'object' => new stdClass,
            default => '…',
        };
    }

    /**
     * @param  array<string, mixed>  $body
     */
    private function json(array $body): string
    {
        return (string) json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /**
     * A single-quoted shell word, a quote in it closing the word, escaped, and opening it again.
     */
    private function shell(string $value): string
    {
        return "'".str_replace("'", "'\\''", $value)."'";
    }

    private function php(mixed $value): string
    {
        return match (true) {
            is_array($value) && array_is_list($value) => '['.implode(', ', array_map($this->php(...), $value)).']',
            is_array($value) => '['.implode(', ', array_map(fn (mixed $key, mixed $item): string => $this->php($key).' => '.$this->php($item), array_keys($value), $value)).']',
            is_string($value) => "'".strtr($value, ['\\' => '\\\\', "'" => "\\'"])."'",
            is_bool($value) => $value ? 'true' : 'false',
            $value instanceof stdClass => '(object) []',
            default => (string) json_encode($value),
        };
    }

    private function javascript(mixed $value): string
    {
        return match (true) {
            is_array($value) && array_is_list($value) => '['.implode(', ', array_map($this->javascript(...), $value)).']',
            is_array($value) => '{ '.implode(', ', array_map(fn (mixed $key, mixed $item): string => $this->javascriptKey((string) $key).': '.$this->javascript($item), array_keys($value), $value)).' }',
            is_string($value) => "'".strtr($value, ['\\' => '\\\\', "'" => "\\'", "\n" => '\n', "\r" => '\r'])."'",
            is_bool($value) => $value ? 'true' : 'false',
            $value instanceof stdClass => '{}',
            default => (string) json_encode($value),
        };
    }

    private function javascriptKey(string $key): string
    {
        return preg_match('/^[A-Za-z_$][\w$]*$/', $key) === 1 ? $key : $this->javascript($key);
    }

    /**
     * @return list<mixed>
     */
    private function list(mixed $value): array
    {
        return is_array($value) ? array_values($value) : [];
    }
}
