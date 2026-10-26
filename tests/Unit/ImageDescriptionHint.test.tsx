/// <reference types="node" />
import assert from 'node:assert/strict';
import { renderToStaticMarkup } from 'react-dom/server';
import { test } from 'vite-plus/test';
import { ImagePickerPreviews } from '../../resources/js/components/commit-image-picker';

const image = (name: string) =>
    new File(['image'], name, { type: 'image/png' });

const render = (images: File[], descriptions: string[]) =>
    renderToStaticMarkup(
        <ImagePickerPreviews
            images={images}
            descriptions={descriptions}
            onDescribe={() => {}}
            onRemove={() => {}}
        />,
    );

test('hints at describing images without a description, without blocking anything', () => {
    const one = render([image('a.png'), image('b.png')], ['A chart', ' ']);

    assert.match(
        one,
        /data-test="image-description-hint">Add a description with <span[^>]*>\+ALT<\/span> so screen readers can describe your image\.</,
    );
    assert.equal(one.match(/ring-primary/g)?.length, 1);
    assert.equal(one.match(/aria-describedby=/g)?.length, 1);

    assert.match(
        render([image('a.png'), image('b.png')], ['', '']),
        /can describe your images\./,
    );
});

test('says nothing once every image is described', () => {
    const markup = render([image('a.png')], ['A chart']);

    assert.equal(markup.includes('image-description-hint'), false);
    assert.equal(markup.includes('ring-primary'), false);
    assert.equal(markup.includes('aria-describedby'), false);
});
