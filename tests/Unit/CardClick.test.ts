/// <reference types="node" />
import assert from 'node:assert/strict';
import { test } from 'node:test';
import { cardClickAction } from '../../resources/js/lib/card-click.ts';
import type { CardClick } from '../../resources/js/lib/card-click.ts';

const click: CardClick = {
    button: 0,
    metaKey: false,
    ctrlKey: false,
    isOnCard: true,
};

void test('opens the commit in place on a plain click', () => {
    assert.equal(cardClickAction(click), 'visit');
});

void test('opens the commit in a new tab on a cmd-click, ctrl-click or middle-click', () => {
    assert.equal(cardClickAction({ ...click, metaKey: true }), 'new-tab');
    assert.equal(cardClickAction({ ...click, ctrlKey: true }), 'new-tab');
    assert.equal(cardClickAction({ ...click, button: 1 }), 'new-tab');
});

void test('ignores right-clicks', () => {
    assert.equal(cardClickAction({ ...click, button: 2 }), null);
});

void test('ignores clicks that are not on the card itself, such as on links, buttons or selected text', () => {
    assert.equal(cardClickAction({ ...click, isOnCard: false }), null);
    assert.equal(cardClickAction({ ...click, isOnCard: false, metaKey: true }), null);
    assert.equal(cardClickAction({ ...click, isOnCard: false, button: 1 }), null);
});
