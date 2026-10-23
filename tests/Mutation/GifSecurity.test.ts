import assert from 'node:assert/strict';
import { spawnSync } from 'node:child_process';
import type { SpawnSyncReturns } from 'node:child_process';
import {
    mkdtempSync,
    mkdirSync,
    readFileSync,
    rmSync,
    writeFileSync,
} from 'node:fs';
import { tmpdir } from 'node:os';
import { dirname, join } from 'node:path';
import { test } from 'node:test';
import { fileURLToPath } from 'node:url';
import { phpGifMutations } from './php-gif-mutations.ts';
import type { GifMutation } from './php-gif-mutations.ts';
import { playbackMutations } from './playback-mutations.ts';

const root = fileURLToPath(new URL('../..', import.meta.url));
const temporary = mkdtempSync(join(tmpdir(), 'devnull-gif-mutations-'));
const mutationTarget = 'PEST_MUTATION_TESTING';
const mutationSource = 'PEST_MUTATION_FILE';
const timeoutMilliseconds = 60_000;
const controls = new Set<string>();

function runPhp(
    mutation: GifMutation,
    source: string,
): SpawnSyncReturns<string> {
    const modifiedFile = join(temporary, 'mutated.php');
    writeFileSync(modifiedFile, source);

    return spawnSync(
        'php',
        [
            'vendor/bin/pest',
            '--ci',
            '--compact',
            '--colors=never',
            mutation.test,
        ],
        {
            cwd: root,
            encoding: 'utf8',
            timeout: timeoutMilliseconds,
            env: {
                ...process.env,
                [mutationTarget]: join(root, mutation.source),
                [mutationSource]: modifiedFile,
            },
        },
    );
}

function runPlayback(
    mutation: GifMutation,
    source: string,
): SpawnSyncReturns<string> {
    const modifiedFile = join(temporary, mutation.source);
    const testFile = join(temporary, mutation.test);
    mkdirSync(dirname(modifiedFile), { recursive: true });
    mkdirSync(dirname(testFile), { recursive: true });
    writeFileSync(join(temporary, 'package.json'), '{"type":"module"}');
    writeFileSync(modifiedFile, source);
    writeFileSync(testFile, readFileSync(join(root, mutation.test)));
    const environment = { ...process.env };
    delete environment.NODE_TEST_CONTEXT;

    return spawnSync(
        process.execPath,
        ['--experimental-strip-types', '--test', testFile],
        {
            encoding: 'utf8',
            timeout: timeoutMilliseconds,
            env: environment,
        },
    );
}

function output(result: SpawnSyncReturns<string>): string {
    assert.ifError(result.error);
    assert.notEqual(result.status, null, 'Mutation process did not finish');

    return result.stdout + result.stderr;
}

type Runner = (
    mutation: GifMutation,
    source: string,
) => SpawnSyncReturns<string>;

function verifyControl(
    mutation: GifMutation,
    source: string,
    run: Runner,
): void {
    if (controls.has(mutation.source)) {
        return;
    }

    const control = run(mutation, source);
    const report = output(control);
    assert.equal(control.status, 0, report);
    assert.match(
        report,
        run === runPhp ? /Tests:\s+.*\bpassed\b/ : /\bpass [1-9]\d*/,
        report,
    );
    controls.add(mutation.source);
}

function checkMutation(mutation: GifMutation): void {
    const original = readFileSync(join(root, mutation.source), 'utf8');
    const run = mutation.source.endsWith('.php') ? runPhp : runPlayback;
    verifyControl(mutation, original, run);
    assert.ok(
        original.includes(mutation.before),
        `Mutation no longer matches ${mutation.source}`,
    );
    const result = run(
        mutation,
        original.replace(mutation.before, mutation.after),
    );
    const report = output(result);
    assert.notEqual(
        result.status,
        0,
        `Mutation survived: ${mutation.name}\n${report}`,
    );
    assert.match(
        report,
        run === runPhp ? /Tests:\s+.*\bfailed\b/ : /ERR_ASSERTION/,
        report,
    );
}

await test('GIF security mutation gate', async (context) => {
    context.after(() => rmSync(temporary, { recursive: true, force: true }));

    for (const mutation of [...phpGifMutations, ...playbackMutations]) {
        await context.test(`detects mutation: ${mutation.name}`, () =>
            checkMutation(mutation),
        );
    }
});
