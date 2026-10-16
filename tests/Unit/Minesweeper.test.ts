/// <reference types="node" />
import assert from 'node:assert/strict';
import { test } from 'node:test';
import {
    cellCount,
    chord,
    formatDuration,
    move,
    neighbors,
} from '../../resources/js/lib/minesweeper';

void test('finds the neighbors of a cell within the board', () => {
    assert.deepEqual(neighbors(0), [1, 16, 17]);
    assert.deepEqual(neighbors(17), [0, 1, 2, 16, 18, 32, 33, 34]);
    assert.deepEqual(neighbors(cellCount - 1), [94, 95, 110]);
});

void test('chords a number once its mines are flagged', () => {
    const cells: (number | null)[] = Array.from(
        { length: cellCount },
        () => null,
    );
    cells[17] = 1;
    cells[1] = 1;

    assert.deepEqual(chord(17, cells, new Set()), []);
    assert.deepEqual(chord(17, cells, new Set([0, 2])), []);
    assert.deepEqual(chord(17, cells, new Set([0])), [2, 16, 18, 32, 33, 34]);
});

void test('never chords a hidden or empty cell', () => {
    const cells: (number | null)[] = Array.from(
        { length: cellCount },
        () => null,
    );
    cells[5] = 0;

    assert.deepEqual(chord(4, cells, new Set()), []);
    assert.deepEqual(chord(5, cells, new Set()), []);
});

void test('moves with the arrow keys, stopping at the edges', () => {
    assert.equal(move(0, 'ArrowUp'), 0);
    assert.equal(move(0, 'ArrowLeft'), 0);
    assert.equal(move(0, 'ArrowRight'), 1);
    assert.equal(move(0, 'ArrowDown'), 16);
    assert.equal(move(cellCount - 1, 'ArrowDown'), cellCount - 1);
    assert.equal(move(cellCount - 1, 'ArrowRight'), cellCount - 1);
    assert.equal(move(17, 'ArrowUp'), 1);
    assert.equal(move(17, 'ArrowLeft'), 16);
    assert.equal(move(0, 'Enter'), null);
});

void test('formats the time of a game', () => {
    assert.equal(formatDuration(12_345, false), '12s');
    assert.equal(formatDuration(12_345, true), '12.3s');
    assert.equal(formatDuration(-50, false), '0s');
});
