/// <reference types="node" />
import assert from 'node:assert/strict';
import { Children, isValidElement } from 'react';
import type { ReactNode } from 'react';
import { test } from 'vite-plus/test';
import { LinkifiedText } from '../../resources/js/components/linkified-text';

function keysOf(text: string, mentions: Record<string, string> | null) {
    const fragment = LinkifiedText({ text, mentions });
    const keys: (string | null)[] = [];

    Children.forEach(
        (fragment.props as { children: ReactNode }).children,
        (child) => {
            if (isValidElement(child)) {
                keys.push(child.key);
            }
        },
    );

    return keys;
}

function assertUniqueKeys(keys: (string | null)[]) {
    assert.ok(keys.length > 0);
    assert.ok(
        keys.every((key) => key !== null),
        'every element has a key',
    );
    assert.deepEqual(
        keys.filter((key, index) => keys.indexOf(key) !== index),
        [],
        'no key is shared between siblings',
    );
}

test('gives each /dev/null wordmark its own key around a link', () => {
    assertUniqueKeys(
        keysOf(
            'Shipped /dev/null https://example.com and /dev/null again',
            null,
        ),
    );
});

test('keeps keys unique when wordmarks, mentions and links are mixed', () => {
    assertUniqueKeys(
        keysOf(
            '/dev/null by @nuno, /dev/null at https://dev-null.sh, and /dev/null again',
            { nuno: '01a14f9b-9d9b-730b-a48f-9a15ca238694' },
        ),
    );
});
