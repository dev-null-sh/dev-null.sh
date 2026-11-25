import assert from 'node:assert/strict';
import { test } from 'node:test';
import { matchGuilds } from '../../resources/js/lib/guild-search.ts';
import type { Guild } from '../../resources/js/types/guild';

function guild(handle: string, name: string, member_count = 1): Guild {
    return {
        id: handle,
        handle,
        name,
        description: '',
        emoji: '#',
        icon_url: null,
        member_count,
        is_joined: false,
        is_muted: false,
        is_paused: false,
    };
}

const guilds = [
    guild('laravel', 'Laravel', 40),
    guild('php', 'PHP folks', 90),
    guild('rust', 'Rustaceans', 10),
    guild('larabelles', 'Larabelles', 5),
    guild('frontend', 'Frontend in PHP', 200),
];

await test('finds guilds by handle or name, case-insensitively', () => {
    assert.deepEqual(
        matchGuilds(guilds, 'RUST').map(({ handle }) => handle),
        ['rust'],
    );
    assert.deepEqual(
        matchGuilds(guilds, 'folks').map(({ handle }) => handle),
        ['php'],
    );
});

await test('puts an exact handle first, then handle and name prefixes, then the biggest', () => {
    assert.deepEqual(
        matchGuilds(guilds, 'php').map(({ handle }) => handle),
        ['php', 'frontend'],
    );
    assert.deepEqual(
        matchGuilds(guilds, 'lara').map(({ handle }) => handle),
        ['laravel', 'larabelles'],
    );
});

await test('ignores a leading # and keeps to the limit', () => {
    assert.deepEqual(
        matchGuilds(guilds, ' #rust ').map(({ handle }) => handle),
        ['rust'],
    );
    assert.equal(matchGuilds(guilds, 'a', 2).length, 2);
});

await test('finds nothing for guests, empty terms or developer searches', () => {
    assert.deepEqual(matchGuilds(null, 'php'), []);
    assert.deepEqual(matchGuilds(guilds, ''), []);
    assert.deepEqual(matchGuilds(guilds, '#'), []);
    assert.deepEqual(matchGuilds(guilds, '@php'), []);
});
