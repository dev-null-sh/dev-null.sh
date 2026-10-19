/// <reference types="node" />
import assert from 'node:assert/strict';
import { renderToStaticMarkup } from 'react-dom/server';
import { test } from 'vite-plus/test';
import { BioText } from '../../resources/js/components/bio-text';

function textOf(html: string): string {
    return html.replace(/<[^>]*>/g, '');
}

test('links each tagged GitHub account with its avatar', () => {
    const html = renderToStaticMarkup(
        <BioText bio="Building @laravel, previously @Vercel." />,
    );

    assert.equal(html.match(/data-test="bio-mention"/g)?.length, 2);
    assert.ok(html.includes('href="https://github.com/laravel"'));
    assert.ok(html.includes('src="https://github.com/laravel.png?size=40"'));
    assert.ok(html.includes('href="https://github.com/Vercel"'));
    assert.ok(html.includes('src="https://github.com/Vercel.png?size=40"'));
});

test('opens tagged accounts on GitHub in a new tab', () => {
    const html = renderToStaticMarkup(<BioText bio="Hi @laravel" />);

    assert.ok(html.includes('target="_blank"'));
    assert.ok(html.includes('rel="noopener noreferrer nofollow ugc"'));
    assert.ok(html.includes('aria-label="@laravel on GitHub"'));
});

test('keeps the whole bio as copyable text', () => {
    const bio = '@laravel at the start, then @vercel. And @github';

    assert.equal(textOf(renderToStaticMarkup(<BioText bio={bio} />)), bio);
});

test('leaves emails, package names and domains as plain text', () => {
    const bio = 'Mail me@example.com, I use @inertiajs/react and @vue.js';
    const html = renderToStaticMarkup(<BioText bio={bio} />);

    assert.ok(!html.includes('<a'));
    assert.equal(textOf(html), bio);
});
