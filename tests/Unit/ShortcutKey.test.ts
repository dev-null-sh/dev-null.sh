import assert from 'node:assert/strict';
import { test } from 'node:test';
import { shortcutKey } from '../../resources/js/lib/keyboard.ts';

void test('non-Latin layouts read the letter of the physical key', () => {
    assert.equal(shortcutKey({ key: 'л', code: 'KeyK' }), 'k');
    assert.equal(shortcutKey({ key: 'о', code: 'KeyJ' }), 'j');
    assert.equal(shortcutKey({ key: 'З', code: 'KeyP' }), 'p');
    assert.equal(shortcutKey({ key: 'κ', code: 'KeyK' }), 'k');
    assert.equal(shortcutKey({ key: 'ל', code: 'KeyK' }), 'k');
    assert.equal(shortcutKey({ key: 'ن', code: 'KeyK' }), 'k');
    // Arabic Shift+P types punctuation rather than a letter.
    assert.equal(shortcutKey({ key: '؛', code: 'KeyP' }), 'p');
});

void test('AltGr characters stay what they type', () => {
    // Windows reports AltGr as Ctrl+Alt: Latvian AltGr+K is "ķ", not Ctrl+K.
    assert.equal(shortcutKey({ key: 'ķ', code: 'KeyK', altKey: true }), 'ķ');
    assert.equal(shortcutKey({ key: 'ą', code: 'KeyA', altKey: true }), 'ą');
});

void test('Latin layouts keep the letter they type', () => {
    // AZERTY types "a" on the Q key and "q" on the A key.
    assert.equal(shortcutKey({ key: 'a', code: 'KeyQ' }), 'a');
    assert.equal(shortcutKey({ key: 'q', code: 'KeyA' }), 'q');
    // QWERTZ types "y" on the Z key.
    assert.equal(shortcutKey({ key: 'y', code: 'KeyZ' }), 'y');
    assert.equal(shortcutKey({ key: 'K', code: 'KeyK' }), 'K');
});

void test('symbols and named keys are left alone', () => {
    assert.equal(shortcutKey({ key: '?', code: 'Slash' }), '?');
    assert.equal(shortcutKey({ key: '/', code: 'Period' }), '/');
    assert.equal(shortcutKey({ key: 'Enter', code: 'Enter' }), 'Enter');
    assert.equal(shortcutKey({ key: '1', code: 'KeyQ' }), '1');
});
