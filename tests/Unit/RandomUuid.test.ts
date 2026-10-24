import assert from 'node:assert/strict';
import { afterEach, test } from 'node:test';
import { randomUuid } from '../../resources/js/lib/utils.ts';

const v4 =
    /^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/;

afterEach(() => {
    delete (crypto as { randomUUID?: unknown }).randomUUID;
});

void test('the browser generates the uuid when it can', () => {
    Object.defineProperty(crypto, 'randomUUID', {
        value: () => '11111111-2222-4333-8444-555555555555',
        configurable: true,
        writable: true,
    });

    assert.equal(randomUuid(), '11111111-2222-4333-8444-555555555555');
});

void test('an insecure context still gets a v4 uuid', () => {
    Object.defineProperty(crypto, 'randomUUID', {
        value: undefined,
        configurable: true,
        writable: true,
    });

    const first = randomUuid();
    const second = randomUuid();

    assert.match(first, v4);
    assert.match(second, v4);
    assert.notEqual(first, second);
});
