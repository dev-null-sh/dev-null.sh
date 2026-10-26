/// <reference types="node" />
import assert from 'node:assert/strict';
import * as React from 'react';
import { renderToStaticMarkup } from 'react-dom/server';
import { afterAll, test, vi } from 'vite-plus/test';

// The first index the previews keep is the image whose description is being edited: here, the first one.
let indexes = 0;

vi.doMock('react', () => ({
    ...React,
    useState: (initial: unknown) => [
        initial === null && indexes++ % 2 === 0 ? 0 : initial,
        () => {},
    ],
}));

afterAll(() => vi.doUnmock('react'));

const { ImagePickerPreviews } =
    await import('../../resources/js/components/commit-image-picker');

const image = (name: string) =>
    new File(['image'], name, { type: 'image/png' });

test('points no +ALT button at the hint while a description is being edited and the hint is hidden', () => {
    const markup = renderToStaticMarkup(
        <ImagePickerPreviews
            images={[image('a.png'), image('b.png')]}
            descriptions={['', '']}
            onDescribe={() => {}}
            onRemove={() => {}}
        />,
    );

    assert.equal(markup.includes('data-test="image-description-hint"'), false);
    assert.equal(markup.includes('aria-describedby'), false);
});
