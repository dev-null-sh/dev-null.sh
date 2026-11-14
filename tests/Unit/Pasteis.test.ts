import assert from 'node:assert/strict';
import { test } from 'node:test';
import { pasteisParts } from '../../resources/js/lib/pasteis.ts';

test('draws pastel, pasteis and pastéis as pastéis, in any case', () => {
    assert.deepEqual(pasteisParts('one Pastel, two PASTÉIS, more pasteis'), [
        { text: 'one ', pastel: false },
        { text: 'Pastel', pastel: true },
        { text: ', two ', pastel: false },
        { text: 'PASTÉIS', pastel: true },
        { text: ', more ', pastel: false },
        { text: 'pasteis', pastel: true },
    ]);
});

test('leaves the word inside another word alone', () => {
    assert.deepEqual(pasteisParts('pastels and pastelaria'), [
        { text: 'pastels and pastelaria', pastel: false },
    ]);
});

test('keeps a word escaped with a backslash, without the backslash', () => {
    assert.deepEqual(pasteisParts('I love \\pastel de nata, not \\Pastéis'), [
        { text: 'I love pastel de nata, not Pastéis', pastel: false },
    ]);
});

test('escapes only the word right after the backslash', () => {
    assert.deepEqual(pasteisParts('\\pastel or pastel'), [
        { text: 'pastel or ', pastel: false },
        { text: 'pastel', pastel: true },
    ]);
});

test('leaves a backslash that follows a word or another backslash as written', () => {
    assert.deepEqual(pasteisParts('path\\pastel and \\\\pastel'), [
        { text: 'path\\pastel and \\\\pastel', pastel: false },
    ]);
});

test('keeps text without a pastel whole', () => {
    assert.deepEqual(pasteisParts(''), []);
    assert.deepEqual(pasteisParts('custard'), [{ text: 'custard', pastel: false }]);
});
