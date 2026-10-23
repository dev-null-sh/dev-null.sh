import type { Outcome } from '@/lib/php/sandbox';

export type Run = Outcome & { ms: number };

const TIME_LIMIT_MS = 5000;

let compiled: Promise<WebAssembly.Module> | null = null;

/**
 * Downloads and compiles PHP the first time someone runs a code block, however many code blocks they run after.
 */
function load(): Promise<WebAssembly.Module> {
    compiled ??= fetch(new URL('./php/php.wasm', import.meta.url))
        .then((response) => response.arrayBuffer())
        .then((buffer) => WebAssembly.compile(buffer))
        .catch((error: unknown) => {
            compiled = null;

            throw error;
        });

    return compiled;
}

/**
 * The download, or nothing as soon as the run is stopped, even before it began, so Stop works while PHP is still on
 * its way.
 */
function loadUnless(signal: AbortSignal): Promise<WebAssembly.Module | null> {
    return new Promise((resolve, reject) => {
        if (signal.aborted) {
            resolve(null);

            return;
        }

        signal.addEventListener('abort', () => resolve(null), { once: true });
        load().then(resolve, reject);
    });
}

/**
 * Starts the worker that runs PHP. In development, Vite serves it from an origin of its own, which a page can't start
 * a worker from but can import a module from, so it starts from a script of the page's that imports it.
 */
function spawn(): Worker {
    try {
        return new Worker(new URL('./php/worker.ts', import.meta.url), {
            type: 'module',
        });
    } catch (error) {
        if (!import.meta.env.DEV) {
            throw error;
        }

        const script = URL.createObjectURL(
            new Blob(
                [
                    `import ${JSON.stringify(import.meta.resolve('./php/worker.ts'))};`,
                ],
                { type: 'text/javascript' },
            ),
        );
        const worker = new Worker(script, { type: 'module' });

        URL.revokeObjectURL(script);

        return worker;
    }
}

/**
 * Runs the code in a fresh PHP in a worker of its own, thrown away afterwards, after five seconds at the latest.
 */
export async function run(
    code: string,
    onLine: (line: string) => void,
    signal: AbortSignal,
): Promise<Run> {
    const module = await loadUnless(signal);
    const started = performance.now();

    const ended = (outcome: Partial<Outcome>): Run => ({
        exitCode: null,
        failure: null,
        isTruncated: false,
        ...outcome,
        ms: performance.now() - started,
    });

    if (module === null || signal.aborted) {
        return ended({ failure: 'Stopped.' });
    }

    return new Promise((resolve) => {
        const worker = spawn();

        const finish = (outcome: Partial<Outcome>): void => {
            clearTimeout(timer);
            signal.removeEventListener('abort', stop);
            worker.terminate();
            resolve(ended(outcome));
        };

        const stop = (): void => finish({ failure: 'Stopped.' });

        const timer = setTimeout(
            () =>
                finish({
                    failure: `Killed after ${TIME_LIMIT_MS / 1000} seconds.`,
                }),
            TIME_LIMIT_MS,
        );

        signal.addEventListener('abort', stop, { once: true });

        worker.onmessage = ({
            data,
        }: MessageEvent<
            { type: 'line'; line: string } | { type: 'done'; outcome: Outcome }
        >) => {
            if (data.type === 'line') {
                onLine(data.line);
            } else {
                finish(data.outcome);
            }
        };

        worker.onerror = (event) =>
            finish({ failure: event.message || 'PHP could not start.' });

        worker.postMessage({ module, code });
    });
}

/**
 * How a run ended, as the line under its output words it. The build is 32-bit, which shows in integers past
 * 2,147,483,647 and dates past 2038, so the line says so.
 */
export function ending(run: Run): string {
    const took =
        run.ms < 1000
            ? `${Math.round(run.ms)} ms`
            : `${(run.ms / 1000).toFixed(1)} s`;

    return [
        run.failure ?? `exit ${run.exitCode ?? 0}`,
        run.failure === null ? took : null,
        run.isTruncated ? 'output cut' : null,
        'PHP 8.5 (32-bit) in your browser',
    ]
        .filter(Boolean)
        .join(' · ');
}
