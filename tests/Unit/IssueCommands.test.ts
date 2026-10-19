import assert from 'node:assert/strict';
import { test } from 'node:test';
import {
    hintedCommand,
    suggestedCommands,
    typedCommand,
} from '../../resources/js/lib/issue-commands.ts';

test('suggests commands only while the draft is a slash and the start of a name', () => {
    const names = (body: string) =>
        suggestedCommands(body).map((command) => command.name);

    assert.deepEqual(names('/'), ['/feature', '/bug']);
    assert.deepEqual(names('/f'), ['/feature']);
    assert.deepEqual(names('/bug'), ['/bug']);
    assert.deepEqual(names('/deploy'), []);
    assert.deepEqual(names('/bug '), []);
    assert.deepEqual(names('Shipped /bug fixes'), []);
    assert.deepEqual(names(''), []);
});

test('a complete command and a space or new line becomes an issue without the command', () => {
    assert.deepEqual(typedCommand('/bug '), { kind: 'bug', body: '' });
    assert.deepEqual(typedCommand('/feature Dark mode'), {
        kind: 'feature',
        body: 'Dark mode',
    });
    assert.deepEqual(typedCommand('/bug\nSteps'), {
        kind: 'bug',
        body: 'Steps',
    });
    assert.equal(typedCommand('/bug'), null);
    assert.equal(typedCommand('/bugs are fixed'), null);
    assert.equal(typedCommand('Fixed a /bug today'), null);
});

test('hints the command a plain draft mentions', () => {
    const name = (body: string) => hintedCommand(body)?.name ?? null;

    assert.equal(name('The search has a bug'), '/bug');
    assert.equal(name('Bugs everywhere today'), '/bug');
    assert.equal(name('Feature idea: dark mode for the CLI'), '/feature');
    assert.equal(name('Would love these features'), '/feature');
    assert.equal(name('Debugging the parser'), null);
    assert.equal(name('Featured on the front page'), null);
    assert.equal(name('/bug already a command'), null);
    assert.equal(name('Shipped the parser'), null);
});
