import createPhp, { type FsNode, type PhpModule } from './php.mjs';

export type Outcome = {
    exitCode: number | null;
    failure: string | null;
    isTruncated: boolean;
};

export const OUTPUT_LIMIT = 64 * 1024;

export const LINE_LIMIT = 1000;

/**
 * Files live in the worker's memory rather than PHP's, so a run gets this much of them, names included, before
 * they stop growing, as on a full disk.
 */
const FILE_LIMIT = 16 * 1024 * 1024;

/**
 * Roughly what a file, directory or link costs before its name and contents.
 */
const NODE_SIZE = 1024;

const ENOSPC = 51;

const O_APPEND = 1024;

/**
 * Each open directory keeps its own copy of the listing, so a run gets this many handles, of any kind, at once.
 */
const HANDLE_LIMIT = 256;

const INI = [
    'display_errors=1',
    'html_errors=0',
    'log_errors=0',
    'error_reporting=E_ALL',
    'zend.assertions=1',
    'allow_url_fopen=0',
    'allow_url_include=0',
    'date.timezone=UTC',
    'output_buffering=0',
    'implicit_flush=1',
    // Emscripten makes up an address for every host name it's asked about, and remembers them all.
    'disable_functions=exec,passthru,shell_exec,system,proc_open,popen,pcntl_exec,mail,fsockopen,pfsockopen,stream_socket_client,stream_socket_server,stream_socket_pair,gethostbyname,gethostbynamel,gethostbyaddr,dl,set_time_limit',
].join('\n');

/**
 * Code in commits usually leaves out the opening tag, which PHP would otherwise print as text. It goes on the
 * first line, so the line numbers in errors still match the code block.
 */
export function withOpeningTag(code: string): string {
    return /^\s*<\?(?:php\b|=)/i.test(code) ? code : `<?php ${code}`;
}

/**
 * Words a crash the way someone reading a code block would want it, rather than the way Emscripten throws it.
 */
export function failureOf(error: unknown): string {
    const message = error instanceof Error ? error.message : String(error);

    if (/OOM|out of memory/i.test(message)) {
        return 'PHP ran out of memory (128 MB).';
    }

    // Fibers switch stacks with getcontext() and friends, which WebAssembly doesn't have.
    if (/missing function: (get|make|swap)context/.test(message)) {
        return 'Fibers need stack switching, which PHP in WebAssembly can’t do.';
    }

    return message;
}

/**
 * Counts what the run's files take, from every file, directory and link it makes, and every name it gives one, to
 * every byte a file grows by, whether written past its end, after seeking past it or by truncating it longer, and
 * refuses past the limit, the way a full disk would. Truncating a file shorter, as opening it with "w" does, gives
 * the space back; deleting it doesn't, since an open handle can keep it.
 */
function limitFiles(php: PhpModule): void {
    const { FS } = php;
    const [mknod, symlink, rename, write, doTruncate] = [
        FS.mknod.bind(FS),
        FS.symlink.bind(FS),
        FS.rename.bind(FS),
        FS.write.bind(FS),
        FS.doTruncate.bind(FS),
    ];
    let taken = 0;

    const charge = <T>(bytes: number, call: () => T): T => {
        if (taken + bytes > FILE_LIMIT) {
            throw new FS.ErrnoError(ENOSPC);
        }

        taken += bytes;

        try {
            return call();
        } catch (error) {
            taken -= bytes;

            throw error;
        }
    };

    const resized = (node: FsNode, size: number): number =>
        FS.isFile(node.mode) ? size - node.usedBytes : 0;

    FS.mknod = (path, mode, dev) =>
        charge(NODE_SIZE + path.length, () => mknod(path, mode, dev));

    FS.symlink = (target, path) =>
        charge(NODE_SIZE + target.length + path.length, () =>
            symlink(target, path),
        );

    FS.rename = (from, to) => charge(to.length, () => rename(from, to));

    FS.write = (stream, buffer, offset, length, position, canOwn) => {
        const start =
            position ??
            (stream.flags & O_APPEND ? stream.node.usedBytes : stream.position);

        return charge(Math.max(resized(stream.node, start + length), 0), () =>
            write(stream, buffer, offset, length, position, canOwn),
        );
    };

    FS.doTruncate = (stream, node, length) =>
        charge(resized(node, length), () => doTruncate(stream, node, length));
}

/**
 * Starts a brand-new PHP, failing rather than waiting forever when the browser can't give it its memory.
 */
function boot(
    module: WebAssembly.Module,
    write: (byte: number) => void,
): Promise<PhpModule> {
    return new Promise((resolve, reject) => {
        createPhp({
            instantiateWasm(imports, done) {
                WebAssembly.instantiate(module, imports).then(
                    (instance) => done(instance, module),
                    reject,
                );

                return {};
            },
            stdout: write,
            stderr: write,
            // Emscripten's own messages, such as an abort, which the thrown error words better.
            printErr: () => undefined,
        }).then(resolve, reject);
    });
}

/**
 * Runs the code in a brand-new PHP and hands its output over line by line, warnings and errors included, as PHP
 * prints them. Output is taken a byte at a time and stops at the limits, so even a line that never ends can't fill
 * the memory PHP runs in.
 */
export async function execute(
    module: WebAssembly.Module,
    code: string,
    onLine: (line: string) => void,
): Promise<Outcome> {
    const decoder = new TextDecoder();
    let pending: number[] = [];
    let bytes = 0;
    let lines = 0;
    let isTruncated = false;

    const line = (): void => {
        if (lines === LINE_LIMIT) {
            isTruncated = true;
        } else {
            onLine(decoder.decode(new Uint8Array(pending)));
            lines++;
        }

        pending = [];
    };

    // Output and errors share the lines, in the order PHP writes them, as in a terminal.
    const write = (byte: number): void => {
        if (isTruncated) {
            return;
        }

        if (byte === 10) {
            line();

            return;
        }

        pending.push(byte);

        if (++bytes >= OUTPUT_LIMIT) {
            line();
            isTruncated = true;
        }
    };

    let php: PhpModule;

    try {
        php = await boot(module, write);
    } catch (error) {
        return {
            exitCode: null,
            failure: `PHP could not start: ${failureOf(error)}`,
            isTruncated,
        };
    }

    php.FS.mkdirTree('/usr/local/lib');
    php.FS.writeFile('/usr/local/lib/php.ini', INI);
    php.FS.writeFile('/snippet.php', withOpeningTag(code));
    // A terminal keeps what it's written in memory until a newline that may never come. Writing to one makes a file.
    php.FS.unlink('/dev/tty');
    php.FS.unlink('/dev/tty1');
    php.FS.MAX_OPEN_FDS = HANDLE_LIMIT;
    limitFiles(php);

    let exitCode: number | null = null;
    let failure: string | null = null;

    try {
        exitCode = php.ccall('phpw', 'number', ['string'], ['/snippet.php']);
    } catch (error) {
        failure = failureOf(error);
    }

    if (pending.length > 0 && !isTruncated) {
        line();
    }

    return { exitCode, failure, isTruncated };
}
