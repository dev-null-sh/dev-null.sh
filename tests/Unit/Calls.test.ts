import assert from 'node:assert/strict';
import { test } from 'node:test';
import { callUrl } from '../../resources/js/lib/calls.ts';

test('a call is a new meet.jit.si room with an unguessable name', () => {
    assert.match(callUrl(), /^https:\/\/meet\.jit\.si\/devnull-[0-9a-f]{32}$/);
    assert.notEqual(callUrl(), callUrl());
});
