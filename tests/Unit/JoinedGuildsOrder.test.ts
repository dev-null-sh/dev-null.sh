import assert from 'node:assert/strict';
import { test } from 'node:test';
import { inOpeningOrder } from '../../resources/js/lib/joined-guilds-order.ts';
import type { JoinedGuild } from '../../resources/js/types/guild.ts';

const guild = (handle: string, missedCommits: number): JoinedGuild => ({
    handle,
    name: handle,
    emoji: '🦀',
    icon_url: null,
    missed_commits: missedCommits,
});

const handles = (guilds: JoinedGuild[]): string[] =>
    guilds.map((joinedGuild) => joinedGuild.handle);

await test('keeps a guild in its row after its missed commits are cleared', () => {
    const resorted = [guild('php', 5), guild('go', 0), guild('rust', 0)];

    assert.deepEqual(handles(inOpeningOrder(resorted, ['rust', 'php', 'go'])), [
        'rust',
        'php',
        'go',
    ]);
});

await test('adds a newly joined guild below the guilds already listed', () => {
    const joined = [guild('zig', 9), guild('rust', 1)];

    assert.deepEqual(handles(inOpeningOrder(joined, ['rust'])), [
        'rust',
        'zig',
    ]);
});

await test('drops a guild the reader left', () => {
    assert.deepEqual(
        handles(inOpeningOrder([guild('php', 0)], ['rust', 'php'])),
        ['php'],
    );
});
