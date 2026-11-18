import assert from 'node:assert/strict';
import { test } from 'vite-plus/test';
import {
    ambiguousColorInDraft,
    hashtagPattern,
    isHashtagHandle,
    typedHashtagPattern,
} from '../../resources/js/lib/hashtags';

const hashtags = (text: string) =>
    [...text.matchAll(hashtagPattern)].map((match) => match[0]);

test('finds hashtags that start with numbers when a letter follows them', () => {
    assert.deepEqual(
        hashtags(
            'New #3DPrinting setup, #100DaysOfCode and #1st #jobs #3d3d3d_x',
        ),
        ['#3DPrinting', '#100DaysOfCode', '#1st', '#jobs', '#3d3d3d_x'],
    );
});

test('leaves issue references and hex colors that start with numbers alone', () => {
    assert.deepEqual(
        hashtags(
            '#123 #0ff #2FA #0ffc #3d3d3d. #1a2b3c4d #3_d #3d3d3d_ #2fa__',
        ),
        [],
    );
});

test('accepts a guild handle that starts with numbers but not a number or a color', () => {
    assert.equal(isHashtagHandle('3dprinting'), true);
    assert.equal(isHashtagHandle('123developers'), true);
    assert.equal(isHashtagHandle('2024'), false);
    assert.equal(isHashtagHandle('2fa'), false);
    assert.equal(isHashtagHandle('2fa_'), false);
    assert.equal(isHashtagHandle('1a2b3c'), false);
    assert.equal(isHashtagHandle('_jobs'), false);
    assert.equal(isHashtagHandle(`3${'d'.repeat(59)}`), true);
    assert.equal(isHashtagHandle(`3${'d'.repeat(60)}`), false);
});

test('suggests guilds once a hashtag that starts with numbers has a letter', () => {
    assert.equal('Love #3'.match(typedHashtagPattern), null);
    assert.equal('Love #3d'.match(typedHashtagPattern)?.[1], '3d');
    assert.equal('Love #3dprint'.match(typedHashtagPattern)?.[1], '3dprint');
    assert.equal('Love #'.match(typedHashtagPattern)?.[1], '');
});

test('keeps hex colors that start with numbers out of the hashtag swatch prompt', () => {
    assert.equal(ambiguousColorInDraft('Paint it #3d3d3d'), undefined);
    assert.deepEqual(ambiguousColorInDraft('Paint it #dead'), {
        index: 9,
        hex: '#dead',
    });
});
