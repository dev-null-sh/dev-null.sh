import assert from 'node:assert/strict';
import { test } from 'node:test';
import { guildFor } from '../../resources/js/lib/guilds.ts';

const guilds = [
    { handle: 'go', aliases: ['golang', 'gopher'] },
    { handle: 'rust', aliases: [] },
];

await test('finds a guild by its handle or by one of its aliases', () => {
    assert.equal(guildFor(guilds, 'go'), guilds[0]);
    assert.equal(guildFor(guilds, 'golang'), guilds[0]);
    assert.equal(guildFor(guilds, 'gopher'), guilds[0]);
    assert.equal(guildFor(guilds, 'rust'), guilds[1]);
});

await test('finds no guild for a hashtag nobody claimed, or without the guilds', () => {
    assert.equal(guildFor(guilds, 'golanguage'), undefined);
    assert.equal(guildFor(null, 'go'), undefined);
    assert.equal(guildFor(undefined, 'go'), undefined);
});
