import assert from 'node:assert/strict';
import { test } from 'node:test';
import { isEmojiOnly } from '../../resources/js/lib/emoji-only.ts';

await test('counts a message of only emoji, spaces and line breaks', () => {
    assert.equal(isEmojiOnly('🙏'), true);
    assert.equal(isEmojiOnly('🔥🔥 🚀'), true);
    assert.equal(isEmojiOnly(' 🎉\n🎉 '), true);
});

await test('counts skin tones, families, flags, keycaps and emoji-styled symbols', () => {
    assert.equal(isEmojiOnly('👍🏽'), true);
    assert.equal(isEmojiOnly('👨‍👩‍👧'), true);
    assert.equal(isEmojiOnly('🇳🇱'), true);
    assert.equal(isEmojiOnly('🏴󠁧󠁢󠁳󠁣󠁴󠁿'), true);
    assert.equal(isEmojiOnly('1️⃣'), true);
    assert.equal(isEmojiOnly('❤️'), true);
});

await test('does not count text, digits, or symbols drawn as text', () => {
    assert.equal(isEmojiOnly('thanks 🙏'), false);
    assert.equal(isEmojiOnly('🙏!'), false);
    assert.equal(isEmojiOnly('42'), false);
    assert.equal(isEmojiOnly('#'), false);
    assert.equal(isEmojiOnly('©'), false);
    assert.equal(isEmojiOnly('™'), false);
    assert.equal(isEmojiOnly(':)'), false);
});

await test('does not count an empty or blank message', () => {
    assert.equal(isEmojiOnly(''), false);
    assert.equal(isEmojiOnly('  \n '), false);
});
