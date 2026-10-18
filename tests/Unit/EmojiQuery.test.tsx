/// <reference types="node" />
import assert from 'node:assert/strict';
import { test } from 'vite-plus/test';
import { emojiQueryBefore } from '../../resources/js/lib/emoji';

test('finds the shortcode being typed and where its colon is', () => {
    assert.deepEqual(emojiQueryBefore(':bu'), { start: 0, query: 'bu' });
    assert.deepEqual(emojiQueryBefore('Tabs :+1'), { start: 5, query: '+1' });
});

test('waits for two characters so times like 10:30 stay quiet', () => {
    assert.equal(emojiQueryBefore(':b'), null);
    assert.equal(emojiQueryBefore('at 10:30'), null);
    assert.equal(emojiQueryBefore('done :rocket: '), null);
});

test('ignores shortcodes typed in code', () => {
    assert.equal(emojiQueryBefore('run `npm :bu'), null);
    assert.deepEqual(emojiQueryBefore('`npm` :bu'), { start: 6, query: 'bu' });
});
