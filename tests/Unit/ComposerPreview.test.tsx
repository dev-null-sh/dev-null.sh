/// <reference types="node" />
import assert from 'node:assert/strict';
import { test } from 'node:test';
import type { KeyboardEvent } from 'react';
import { renderToStaticMarkup } from 'react-dom/server';
import {
    useComposerPreview,
    type ComposerPreview,
} from '../../resources/js/components/composer-preview';

function previewFor(options: {
    isSubmittable: boolean;
    onAddPart?: () => void;
}): ComposerPreview {
    const previews: ComposerPreview[] = [];

    function Composer() {
        previews.push(
            useComposerPreview({
                body: 'Draft commit',
                textareaRef: { current: null },
                ...options,
            }),
        );

        return null;
    }

    renderToStaticMarkup(<Composer />);
    const preview = previews[0];
    assert.ok(preview);

    return preview;
}

for (const modifier of ['ctrlKey', 'metaKey'] as const) {
    for (const shiftKey of [false, true]) {
        void test(`${modifier} + ${shiftKey ? 'Shift + ' : ''}Enter ${shiftKey ? 'adds a series part' : 'publishes'} from preview`, () => {
            let added = 0;
            let submitted = 0;
            let prevented = 0;
            const preview = previewFor({
                isSubmittable: true,
                onAddPart: () => added++,
            });

            preview.onPreviewKeyDown({
                key: 'Enter',
                [modifier]: true,
                shiftKey,
                preventDefault: () => prevented++,
                currentTarget: {
                    closest: () => ({ requestSubmit: () => submitted++ }),
                },
            } as unknown as KeyboardEvent<HTMLDivElement>);

            assert.equal(added, shiftKey ? 1 : 0);
            assert.equal(submitted, shiftKey ? 0 : 1);
            assert.equal(prevented, 1);
        });
    }
}

void test('does not publish an incomplete series from preview', () => {
    previewFor({ isSubmittable: false }).onPreviewKeyDown({
        key: 'Enter',
        ctrlKey: true,
        shiftKey: false,
        preventDefault: () => assert.fail('must not submit'),
    } as unknown as KeyboardEvent<HTMLDivElement>);
});

void test('does not publish a reply with the add-part shortcut', () => {
    previewFor({ isSubmittable: true }).onPreviewKeyDown({
        key: 'Enter',
        ctrlKey: true,
        shiftKey: true,
        preventDefault: () => assert.fail('must not submit'),
    } as unknown as KeyboardEvent<HTMLDivElement>);
});
