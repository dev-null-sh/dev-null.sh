/// <reference types="node" />
import assert from 'node:assert/strict';
import { test } from 'vite-plus/test';
import { issueCommands } from '../../resources/js/lib/issue-commands';
import {
    expandTextCommands,
    expansionCommandBeforeCaret,
    suggestedExpansions,
    textExpansions,
} from '../../resources/js/lib/text-expansions';

test('each expansion has its own command, a slug no issue command uses, and a text to insert', () => {
    const names = textExpansions.map(({ name }) => name);

    assert.equal(new Set(names).size, names.length);

    for (const { name, text } of textExpansions) {
        assert.match(name, /^\/[a-z0-9]+(?:-[a-z0-9]+)*$/, name);
        assert.ok(
            !issueCommands.some((command) => command.name === name),
            name,
        );
        assert.ok(text !== '' && text.trim() === text, name);
    }
});

test('finds the command being typed at the start of the text or after a space, while an expansion starts with it', () => {
    assert.deepEqual(expansionCommandBeforeCaret('/'), {
        start: 0,
        query: '',
    });
    assert.deepEqual(expansionCommandBeforeCaret('it broke\n/ta'), {
        start: 9,
        query: 'ta',
    });
    for (const before of [
        '/deploy',
        '/shrug ',
        'and/sh',
        'https://example.com/sh',
        '`/sh',
        '```\n/sh',
    ]) {
        assert.equal(expansionCommandBeforeCaret(before), null, before);
    }
});

test('suggests expansions only while the draft is a slash and the start of a command', () => {
    const commands = (body: string) =>
        suggestedExpansions(body).map(({ name }) => name);

    assert.deepEqual(commands('/'), ['/shrug', '/tableflip']);
    assert.deepEqual(commands('/t'), ['/tableflip']);
    assert.deepEqual(commands('/shrugs'), []);
    assert.deepEqual(commands('ok /sh'), []);
});

test('turns a typed command into its text once a space or a new line follows it', () => {
    assert.deepEqual(expandTextCommands('/shrug ', 7), {
        text: '¯\\_(ツ)_/¯ ',
        caret: 10,
    });
    assert.deepEqual(expandTextCommands('/tableflip\n', 11), {
        text: '(╯°□°)╯︵ ┻━┻\n',
        caret: 13,
    });
});

test('turns a command typed after other words, and keeps the caret after the text', () => {
    assert.deepEqual(
        expandTextCommands('it broke again /tableflip  later', 26),
        {
            text: 'it broke again (╯°□°)╯︵ ┻━┻  later',
            caret: 28,
        },
    );
});

test('keeps the caret after the text when the command is typed before a space already there', () => {
    assert.deepEqual(expandTextCommands('/shrug later', 6), {
        text: '¯\\_(ツ)_/¯ later',
        caret: 9,
    });
});

test('moves the caret after the text when the letter typed completes the command it is in', () => {
    assert.deepEqual(expandTextCommands('/shrug later', 3), {
        text: '¯\\_(ツ)_/¯ later',
        caret: 9,
    });
});

test('shifts the caret by every text before it, code included', () => {
    assert.deepEqual(expandTextCommands('/shrug and /tableflip ', 22), {
        text: '¯\\_(ツ)_/¯ and (╯°□°)╯︵ ┻━┻ ',
        caret: 27,
    });
    assert.deepEqual(expandTextCommands('run `x` /shrug ', 15), {
        text: 'run `x` ¯\\_(ツ)_/¯ ',
        caret: 18,
    });
    assert.deepEqual(expandTextCommands('/shrug then', 0), {
        text: '¯\\_(ツ)_/¯ then',
        caret: 0,
    });
});

test('keeps the command being typed until a space or a new line ends it', () => {
    assert.deepEqual(expandTextCommands('/shrug', 6), {
        text: '/shrug',
        caret: 6,
    });
    assert.deepEqual(expandTextCommands('/shr ', 5), {
        text: '/shr ',
        caret: 5,
    });
});

test('leaves commands inside a word, a link or a longer command untouched', () => {
    for (const text of [
        'and/shrug ',
        'https://example.com/shrug ',
        '/shrugging ',
        '/shrug-off ',
        '/tableflips ',
    ]) {
        assert.deepEqual(expandTextCommands(text, text.length), {
            text,
            caret: text.length,
        });
    }
});

test('leaves commands in code untouched', () => {
    const text = 'run `/shrug ` or\n```\n/tableflip \n```\n';
    assert.deepEqual(expandTextCommands(text, text.length), {
        text,
        caret: text.length,
    });
});
