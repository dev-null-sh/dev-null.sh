import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { mock, test } from 'node:test';
import { ending, run, type Run } from '../../resources/js/lib/php.ts';
import {
    execute,
    failureOf,
    LINE_LIMIT,
    OUTPUT_LIMIT,
    withOpeningTag,
} from '../../resources/js/lib/php/sandbox.ts';

const bytes = readFileSync(
    new URL('../../resources/js/lib/php/php.wasm', import.meta.url),
);

/**
 * Stands in for the browser's Worker, so a test can play the worker's side of a run.
 */
class FakeWorker {
    static last: FakeWorker | null = null;

    onmessage: ((event: { data: unknown }) => void) | null = null;

    onerror: ((event: { message: string }) => void) | null = null;

    posted: unknown[] = [];

    isTerminated = false;

    constructor() {
        FakeWorker.last = this;
    }

    postMessage(message: unknown): void {
        this.posted.push(message);
    }

    terminate(): void {
        this.isTerminated = true;
    }

    emit(data: unknown): void {
        this.onmessage?.({ data });
    }
}

let release = (): void => undefined;
const downloaded = new Promise<void>((resolve) => {
    release = resolve;
});

// Emscripten's glue looks for a worker scope before it decides where it runs, and the download waits for the tests.
Object.assign(globalThis, {
    WorkerGlobalScope: function WorkerGlobalScope() {},
    self: globalThis,
    Worker: FakeWorker,
    fetch: async () => {
        await downloaded;

        return new Response(bytes);
    },
});

const module = await WebAssembly.compile(bytes);

/**
 * Runs the code the way a code block's worker does, in the real PHP 8.5 WebAssembly build.
 */
async function php(code: string) {
    const lines: string[] = [];
    const outcome = await execute(module, code, (line) => lines.push(line));

    return { ...outcome, lines, output: lines.join('\n') };
}

/**
 * Starts a run and waits for its worker, once PHP has downloaded.
 */
async function started(code: string, signal: AbortSignal) {
    const lines: string[] = [];

    FakeWorker.last = null;

    const pending = run(code, (line) => lines.push(line), signal);

    for (let tick = 0; FakeWorker.last === null; tick++) {
        assert.ok(tick < 100, 'The run never started a worker.');
        await new Promise((resolve) => setImmediate(resolve));
    }

    return { pending, lines, worker: FakeWorker.last as FakeWorker };
}

/**
 * How the run ended, or null while it's still going, so a run that never ends fails the test instead of hanging it.
 */
function settled(pending: Promise<Run>): Promise<Run | null> {
    return Promise.race([
        pending,
        new Promise<null>((resolve) => setImmediate(() => resolve(null))),
    ]);
}

void test('stops a run that was stopped before it began, without waiting for PHP', async () => {
    const result = await settled(
        run('echo 1;', () => undefined, AbortSignal.abort()),
    );

    assert.equal(result?.failure, 'Stopped.');
    assert.equal(FakeWorker.last, null);
});

void test('stops a run while PHP is still downloading', async () => {
    const controller = new AbortController();
    const pending = run('echo 1;', () => undefined, controller.signal);

    controller.abort();

    const result = await settled(pending);

    release();

    assert.equal(result?.failure, 'Stopped.');
    assert.equal(FakeWorker.last, null);
});

void test('runs a block in a worker and hands over its lines and how it ended', async () => {
    const { pending, lines, worker } = await started(
        'echo 1;',
        new AbortController().signal,
    );

    const [message] = worker.posted as { module: unknown; code: string }[];

    assert.ok(message.module instanceof WebAssembly.Module);
    assert.equal(message.code, 'echo 1;');

    worker.emit({ type: 'line', line: '1' });
    worker.emit({ type: 'line', line: '2' });
    worker.emit({
        type: 'done',
        outcome: { exitCode: 0, failure: null, isTruncated: false },
    });

    const result = await settled(pending);

    assert.deepEqual(lines, ['1', '2']);
    assert.equal(result?.exitCode, 0);
    assert.equal(result?.failure, null);
    assert.equal(worker.isTerminated, true);
});

void test('stops a run on request and throws its worker away', async () => {
    const controller = new AbortController();
    const { pending, worker } = await started(
        'while (true) {}',
        controller.signal,
    );

    controller.abort();

    assert.equal((await settled(pending))?.failure, 'Stopped.');
    assert.equal(worker.isTerminated, true);
});

void test('kills a run after five seconds', async () => {
    mock.timers.enable({ apis: ['setTimeout'] });

    try {
        const { pending, worker } = await started(
            'while (true) {}',
            new AbortController().signal,
        );

        mock.timers.tick(4999);

        assert.equal(await settled(pending), null);

        mock.timers.tick(1);

        assert.equal(
            (await settled(pending))?.failure,
            'Killed after 5 seconds.',
        );
        assert.equal(worker.isTerminated, true);
    } finally {
        mock.timers.reset();
    }
});

void test('reports a worker that crashes', async () => {
    const { pending, worker } = await started(
        'echo 1;',
        new AbortController().signal,
    );

    worker.onerror?.({ message: 'Uncaught RuntimeError: unreachable' });

    assert.equal(
        (await settled(pending))?.failure,
        'Uncaught RuntimeError: unreachable',
    );
});

void test('says when PHP could not start', async () => {
    // A module that needs an import PHP's glue doesn't have, so the browser can't start it.
    const broken = await WebAssembly.compile(
        new Uint8Array([
            0, 97, 115, 109, 1, 0, 0, 0, 1, 4, 1, 96, 0, 0, 2, 7, 1, 1, 120, 1,
            121, 0, 0,
        ]),
    );
    const { exitCode, failure } = await execute(broken, 'echo 1;', () =>
        assert.fail('PHP never ran'),
    );

    assert.equal(exitCode, null);
    assert.match(failure ?? '', /^PHP could not start: /);
});

void test('runs PHP 8.5, with or without the opening tag', async () => {
    assert.match(
        (await php("echo PHP_VERSION, ' ', ' Hi ' |> trim(...);")).output,
        /^8\.5\.\d+ Hi$/,
    );
    assert.equal((await php("<?php echo 'tagged';")).output, 'tagged');
    assert.equal(withOpeningTag('echo 1;'), '<?php echo 1;');
    assert.equal(withOpeningTag('  <?php echo 1;'), '  <?php echo 1;');
});

void test('is the 32-bit build the ending line says it is', async () => {
    assert.equal((await php('echo PHP_INT_SIZE;')).output, '4');
});

void test('keeps line numbers in errors where the code block has them', async () => {
    const { output, exitCode } = await php(
        "echo 'a';\nthrow new RuntimeException('boom');",
    );

    assert.match(
        output,
        /Uncaught RuntimeException: boom in \/snippet\.php:2/,
    );
    assert.equal(exitCode, 255);
});

void test('reports the exit code', async () => {
    assert.equal((await php('exit(3);')).exitCode, 3);
    assert.equal((await php("echo 'ok';")).exitCode, 0);
});

void test('has no shell, no sockets, no host lookups and no URL wrappers', async () => {
    assert.equal(
        (
            await php(
                "var_dump(function_exists('exec'), function_exists('fsockopen'), function_exists('proc_open'), function_exists('gethostbyname'), ini_get('allow_url_fopen'));",
            )
        ).output,
        'bool(false)\nbool(false)\nbool(false)\nbool(false)\nstring(1) "0"',
    );
});

void test('keeps output and errors in the order PHP writes them, as a terminal does', async () => {
    assert.deepEqual(
        (
            await php(
                'echo "a"; fwrite(fopen("php://stderr", "w"), "e\\n"); echo "b\\n";',
            )
        ).lines,
        ['ae', 'b'],
    );
});

void test('stops output at the limit, even a line that never ends', async () => {
    const { output, isTruncated, exitCode } = await php(
        "echo str_repeat('a', 3_000_000);",
    );

    assert.equal(output.length, OUTPUT_LIMIT);
    assert.equal(isTruncated, true);
    assert.equal(exitCode, 0);
});

void test('stops output at the line limit', async () => {
    const { lines, isTruncated } = await php(
        'for ($i = 0; $i < 5000; $i++) { echo $i, PHP_EOL; }',
    );

    assert.equal(lines.length, LINE_LIMIT);
    assert.equal(lines.at(-1), String(LINE_LIMIT - 1));
    assert.equal(isTruncated, true);
});

void test('keeps files to 16 MB in all, however they grow, the way a full disk would', async () => {
    const { output } = await php(`
        var_dump(file_put_contents('/tmp/a', str_repeat('x', 10 * 1024 * 1024)));
        var_dump(@file_put_contents('/tmp/a', str_repeat('x', 8 * 1024 * 1024), FILE_APPEND));
        var_dump(ftruncate(fopen('/tmp/b', 'w'), 1_000_000_000));
        $sparse = fopen('/tmp/c', 'w');
        fseek($sparse, 1_000_000_000);
        var_dump(@fwrite($sparse, 'x'));
        var_dump(file_put_contents('/tmp/d', str_repeat('x', 5 * 1024 * 1024)));
    `);

    assert.equal(
        output,
        'int(10485760)\nbool(false)\nbool(false)\nbool(false)\nint(5242880)',
    );
});

void test('counts every file, directory and link a run makes against the same 16 MB', async () => {
    const { lines } = await php(`
        for ($made = 0; @mkdir("/tmp/{$made}"); $made++);
        echo $made, PHP_EOL;
        var_dump(@symlink('/tmp/0', '/tmp/link'), @touch('/tmp/file'), @rename('/tmp/' . str_repeat('./', 1000) . '0', '/tmp/' . str_repeat('x', 2000)));
    `);

    assert.ok(Number(lines[0]) > 15_000 && Number(lines[0]) < 16_384);
    assert.deepEqual(lines.slice(1), [
        'bool(false)',
        'bool(false)',
        'bool(false)',
    ]);
});

void test('gives back the space of a file written over, and takes none for a write that fails', async () => {
    const { output } = await php(`
        for ($i = 0; $i < 100; $i++) {
            file_put_contents('/tmp/cache', str_repeat('x', 1024 * 1024)) or print("failed at {$i}\\n");
        }
        $reading = fopen('/tmp/cache', 'r');
        var_dump(@fwrite($reading, str_repeat('x', 8 * 1024 * 1024)), @fwrite($reading, str_repeat('x', 8 * 1024 * 1024)));
        var_dump(file_put_contents('/tmp/big', str_repeat('x', 14 * 1024 * 1024)));
    `);

    assert.equal(output, 'bool(false)\nbool(false)\nint(14680064)');
});

void test('makes a file of a terminal, so what is written to it counts as well', async () => {
    assert.equal(
        (
            await php(
                "var_dump(@fwrite(fopen('/dev/tty', 'w'), str_repeat('a', 20 * 1024 * 1024)));",
            )
        ).output,
        'bool(false)',
    );
});

void test('keeps a run to about 250 open handles', async () => {
    const { output } = await php(
        "for ($open = []; $handle = @opendir('/tmp'); $open[] = $handle); echo count($open);",
    );

    assert.ok(Number(output) > 240 && Number(output) < 256);
});

void test('says why a fiber fails instead of aborting quietly', async () => {
    const { failure } = await php(
        '$fiber = new Fiber(fn () => Fiber::suspend(1)); $fiber->start();',
    );

    assert.equal(
        failure,
        'Fibers need stack switching, which PHP in WebAssembly can’t do.',
    );
    assert.equal(
        failureOf(new Error('Aborted(OOM)')),
        'PHP ran out of memory (128 MB).',
    );
});

void test('words how a run ended', () => {
    const result: Run = {
        exitCode: 0,
        failure: null,
        isTruncated: false,
        ms: 42.4,
    };

    assert.equal(
        ending(result),
        'exit 0 · 42 ms · PHP 8.5 (32-bit) in your browser',
    );
    assert.equal(
        ending({ ...result, exitCode: 3, isTruncated: true, ms: 1500 }),
        'exit 3 · 1.5 s · output cut · PHP 8.5 (32-bit) in your browser',
    );
    assert.equal(
        ending({
            ...result,
            exitCode: null,
            failure: 'Killed after 5 seconds.',
        }),
        'Killed after 5 seconds. · PHP 8.5 (32-bit) in your browser',
    );
});
