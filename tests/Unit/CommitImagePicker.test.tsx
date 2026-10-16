/// <reference types="node" />

import assert from 'node:assert/strict';
import { test } from 'node:test';
import { renderToString } from 'react-dom/server';
import { ImagePickerPreviews } from '../../resources/js/components/commit-image-picker';

const image = (name: string) =>
    new File([new Uint8Array(8)], name, { type: 'image/png' });

void test('renders nothing without attached images', () => {
    assert.equal(
        renderToString(
            <ImagePickerPreviews images={[]} onRemove={() => undefined} />,
        ),
        '',
    );
});

void test('makes every attached image open the preview, with its own remove button', () => {
    const html = renderToString(
        <ImagePickerPreviews
            images={[image('one.png'), image('two.png')]}
            onRemove={() => undefined}
        />,
    );

    assert.equal(
        html.match(/data-test="attached-image-preview-button"/g)?.length,
        2,
    );
    assert.match(html, /aria-label="Preview image 1"/);
    assert.match(html, /aria-label="Remove image 2"/);
    assert.doesNotMatch(html, /data-test="image-viewer"/);
});
