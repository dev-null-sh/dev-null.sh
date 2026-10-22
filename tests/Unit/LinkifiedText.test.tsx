/// <reference types="node" />
import assert from 'node:assert/strict';
import { App } from '@inertiajs/react';
import type { ComponentProps, ReactElement } from 'react';
import { renderToStaticMarkup } from 'react-dom/server';
import { test } from 'vite-plus/test';
import { BodyCounter } from '../../resources/js/components/body-counter';
import { MessageText } from '../../resources/js/components/chat/message-parts';
import { CommitBody } from '../../resources/js/components/commit-body';
import {
    LinkifiedText,
    typedLinks,
} from '../../resources/js/components/linkified-text';
import { bodySegments } from '../../resources/js/lib/code';
import { bodyCharacterCount } from '../../resources/js/lib/commit';

test('counts each prose URL once per occurrence, without discounting code or oversized links', () => {
    const cases: [string, number][] = [
        ['é🚀 hello', 8],
        ['example.com', 11],
        ['https://example.com/long-path-here', 23],
        ['éhttps://example.com/long-path-here', 35],
        ['٤https://example.com/long-path-here', 35],
        ['\u0301https://example.com/long-path-here', 35],
        ['\u203fhttps://example.com/long-path-here', 35],
        ['_https://example.com/long-path-here', 35],
        ['🚀https://example.com/long-path-here', 24],
        [
            'https://example.com/long-path-here https://example.com/long-path-here',
            47,
        ],
        ['(https://example.com/long-path-here).', 26],
        ['https://example.com/a_(long_article).', 24],
        ['example.com/long-path-here', 23],
        ['`https://example.com/long-path-here`', 36],
        ['```\nhttps://example.com/long-path-here\n```', 42],
        ['`https://example.com/long-path-here', 24],
        ['https://', 8],
        ['https://...', 11],
        ['javascript:alert(1)', 19],
        ['https://example.com/' + 'x'.repeat(2028), 23],
        ['https://example.com/' + 'x'.repeat(2029), 2049],
        ['  hello\r\n\r\n\r\nhttps://example.com/long-path-here  ', 30],
    ];
    for (const [body, length] of cases) {
        assert.equal(bodyCharacterCount(body), length, body);
    }
});

test('trims the draft before recognizing fences so code keeps its blank lines', () => {
    const body = '  ```\n' + 'a'.repeat(3990) + '\n'.repeat(6) + '```';

    assert.equal(bodyCharacterCount(body), 4003);
});

test('does not discount a URL attached to a Unicode word character', () => {
    const body = 'a'.repeat(3975) + ' éhttps://example.com/long-path-here';

    assert.equal(bodyCharacterCount(body), 4011);
    assert.deepEqual(typedLinks(body), []);
});

test('the counter discounts commit links but not issue links', () => {
    const body = 'a'.repeat(3976) + ' https://example.com/long-path-here';
    assert.match(
        renderToStaticMarkup(<BodyCounter body={body} />),
        /0 characters left/,
    );
    assert.match(
        renderToStaticMarkup(<BodyCounter body={body} isIssue />),
        /-11 characters left/,
    );
    assert.match(
        renderToStaticMarkup(<BodyCounter body={body + ' x'} />),
        /-2 characters left/,
    );
});

function renderPage(content: ReactElement): string {
    return renderToStaticMarkup(
        <App
            initialComponent={() => content}
            initialPage={
                {
                    component: 'Commit',
                    url: '/',
                    version: null,
                    props: { errors: {}, auth: { user: null, pasteis: null } },
                    clearHistory: false,
                    encryptHistory: false,
                } as unknown as ComponentProps<typeof App>['initialPage']
            }
        />,
    );
}

test('keeps stable mention links alongside shortened URLs and their full copyable text', () => {
    const id = '019a2b3c-4d5e-7f60-8a9b-0c1d2e3f4a5b';
    const url =
        'https://www.example.com/docs/a-very-long-path-that-is-shortened';
    const text = `Thanks @ORIGINAL! See ${url} and @futuredev.`;
    const html = renderToStaticMarkup(
        <LinkifiedText text={text} mentions={{ original: id }} />,
    );

    assert.ok(html.includes(`href="/mentions/${id}"`));
    assert.ok(html.includes('>@ORIGINAL</a>'));
    assert.equal(html.match(/data-test="mention"/g)?.length, 1);
    assert.ok(html.includes(`href="${url}"`));
    assert.ok(html.includes(`title="${url}"`));
    assert.ok(html.includes('aria-hidden="true"'));
    assert.equal(html.replace(/<[^>]*>/g, '').replace('…', ''), text);
});

test('leaves mentions without captured identities as plain text', () => {
    const html = renderToStaticMarkup(
        <LinkifiedText text="Thanks @original!" mentions={null} />,
    );

    assert.equal(html, 'Thanks @original!');
});

test('keeps captured mention identities in regular bodies alongside highlighted code', () => {
    const id = '019a2b3c-4d5e-7f60-8a9b-0c1d2e3f4a5b';
    const html = renderPage(
        <CommitBody
            segments={[
                { type: 'text', text: 'Thanks @ORIGINAL and @unknown! ' },
                { type: 'code', code: '@ORIGINAL' },
                {
                    type: 'block',
                    language: 'php',
                    html: '<span class="hl-comment">// @ORIGINAL</span>',
                },
            ]}
            mentions={{ original: id }}
        />,
    );

    assert.ok(html.includes(`href="/mentions/${id}"`));
    assert.ok(html.includes('>@ORIGINAL</a>'));
    assert.equal(html.match(/data-test="mention"/g)?.length, 1);
    assert.ok(html.includes('and @unknown!'));
    assert.match(html, /<code[^>]*>@ORIGINAL<\/code>/);
    assert.match(
        html,
        /<pre[^>]*><span class="hl-comment">\/\/ @ORIGINAL<\/span>/,
    );
});

const url = 'https://www.example.com/a-long-path-to-the-release-notes';

for (const surface of ['commit', 'remote', 'optimistic remote', 'excerpt']) {
    test(`formats consecutive quote lines with inline content in a ${surface}`, () => {
        const body =
            '> First `code`\r\n> @original https://example.com :smile:\r\nNormal > text\n>Last';
        const mentions = { original: '019a2b3c-4d5e-7f60-8a9b-0c1d2e3f4a5b' };
        const segments = bodySegments(body);
        const html = renderPage(
            surface.includes('remote') ? (
                <MessageText
                    message={{
                        body,
                        mentions,
                        body_segments:
                            surface === 'remote' ? segments : undefined,
                    }}
                    isMine={surface === 'optimistic remote'}
                />
            ) : (
                <CommitBody
                    segments={segments}
                    mentions={mentions}
                    isStatic={surface === 'excerpt'}
                />
            ),
        );

        const quotes = [
            ...html.matchAll(/<blockquote[^>]*>(.*?)<\/blockquote>/gs),
        ];
        assert.equal(quotes.length, 2);
        assert.equal(
            quotes[0][1].replace(/<[^>]+>/g, ''),
            'First code\r\n@original https://example.com 😄',
        );
        assert.match(quotes[0][1], /<code[^>]*>code<\/code>/);
        assert.equal(quotes[1][1], 'Last');
        assert.match(html, /<\/blockquote>Normal &gt; text<blockquote/);
        if (surface === 'excerpt') {
            assert.ok(!html.includes('<a'));
        } else {
            assert.ok(
                quotes[0][1].includes(`href="/mentions/${mentions.original}"`),
            );
            assert.ok(quotes[0][1].includes('href="https://example.com"'));
        }
    });
}

test('only treats prose at a line start as a quote and escapes quoted HTML', () => {
    const html = renderPage(
        <CommitBody
            segments={bodySegments(
                '`code`> literal\n```text\n> fenced\n```\n> <script>alert(1)</script>\n>\n> `> inline`\n\nAfter',
            )}
            mentions={null}
        />,
    );

    assert.equal(html.match(/<blockquote/g)?.length, 1);
    assert.match(html, /<\/code>&gt; literal/);
    assert.match(html, /<pre[^>]*>&gt; fenced<\/pre>/);
    assert.match(
        html,
        /&lt;script&gt;alert\(1\)&lt;\/script&gt;\n\n<code[^>]*>&gt; inline<\/code><\/blockquote>\nAfter/,
    );
    assert.ok(!html.includes('<script>'));
});

test('finds the full preview URL outside inline and fenced code', () => {
    const body = [
        '`https://inline.example.com`',
        '```text',
        'https://block.example.com',
        '```',
        `Read ${url}.`,
    ].join('\n');

    assert.deepEqual(typedLinks(body), [url]);
    assert.deepEqual(typedLinks('`https://inline.example.com`'), []);
});

test('shortens the displayed preview link while retaining the full URL', () => {
    const markup = renderToStaticMarkup(
        <LinkifiedText text={url} mentions={null} />,
    );

    assert.ok(markup.includes(`href="${url}"`));
    assert.ok(markup.includes(`title="${url}"`));
    assert.ok(markup.includes('example.com/a-long-path-to-the'));
    assert.ok(markup.includes('…'));
    assert.equal(
        markup
            .replace(/<span aria-hidden="true"[^>]*>.*?<\/span>/g, '')
            .replace(/<[^>]+>/g, ''),
        url,
    );
});

for (const [text, trailing] of [
    // A feed excerpt ends with an ellipsis right after its last word.
    ['7\nhttps://example.com/launch…', '…'],
    ['He said “https://example.com/launch”.', '”.'],
    ['‘https://example.com/launch’', '’'],
]) {
    test(`leaves an ellipsis or a curly quote out of the link: ${text}`, () => {
        const markup = renderToStaticMarkup(
            <LinkifiedText text={text} mentions={null} />,
        );

        assert.deepEqual(typedLinks(text), ['https://example.com/launch']);
        assert.ok(markup.includes('href="https://example.com/launch"'));
        assert.ok(markup.endsWith(`</a>${trailing}`));
    });
}

test('keeps every distinct card URL in order without joining prose across code', () => {
    assert.deepEqual(
        typedLinks(
            'https://first.example.com`code`https://second.example.com https://first.example.com',
        ),
        ['https://first.example.com', 'https://second.example.com'],
    );
});

for (const body of [
    'before `https://draft.example.com',
    '```text\nhttps://draft.example.com',
]) {
    test(`ignores card URLs in unfinished code: ${body}`, () => {
        assert.deepEqual(typedLinks(body), []);
    });
}

test('renders /dev/null as the wordmark while keeping its text', () => {
    const text = 'piped to /dev/null, then /dev/null. (/dev/null)';
    const markup = renderToStaticMarkup(
        <LinkifiedText text={text} mentions={null} />,
    );

    assert.equal(markup.match(/title="\/dev\/null"/g)?.length, 3);
    assert.equal(markup.replace(/<[^>]+>/g, ''), text);
});

test('leaves /dev/null inside longer paths, words, URLs and usernames as text', () => {
    const text =
        '/dev/nullable /dev/null/log a/dev/null /dev/null_1 /DEV/NULL https://example.com/dev/null @devnull';
    const markup = renderToStaticMarkup(
        <LinkifiedText text={text} mentions={null} />,
    );

    assert.ok(!markup.includes('title="/dev/null"'));
});

const commitUrl =
    'https://dev-null.sh/@Hacksore/commits/01a14a9d-7971-73ea-8f35-135fe5149ca8';

test('shows a dev/null commit link as its author and short hash', () => {
    const text = `same root cause as ${commitUrl}.`;
    const markup = renderToStaticMarkup(
        <LinkifiedText text={text} mentions={null} />,
    );

    assert.ok(
        markup.includes(
            'href="/@Hacksore/commits/01a14a9d-7971-73ea-8f35-135fe5149ca8"',
        ),
    );
    assert.ok(markup.includes(`title="${commitUrl}"`));
    assert.ok(markup.includes('aria-label="@Hacksore’s commit 5149ca8"'));
    assert.equal(
        markup.replace(/<[^>]+>/g, ''),
        `same root cause as ${commitUrl}Hacksore/5149ca8.`,
    );
    assert.equal(
        markup
            .replace(/<span aria-hidden="true"[^>]*>.*?<\/span><\/a>/g, '')
            .replace(/<[^>]+>/g, ''),
        text,
    );
});

test('shows a dev/null commit link as its author and short hash without a link when static', () => {
    const text = `same root cause as ${commitUrl}.`;
    const markup = renderToStaticMarkup(
        <LinkifiedText text={text} mentions={null} isStatic />,
    );

    assert.ok(!markup.includes('<a'));
    assert.ok(markup.includes(`title="${commitUrl}"`));
    assert.equal(
        markup.replace(/<[^>]+>/g, ''),
        `same root cause as ${commitUrl}Hacksore/5149ca8.`,
    );
});

for (const link of [
    'https://dev-null.sh/@Hacksore/commits/01a14a9d-7971-73ea-8f35-135fe5149ca8/photos/1',
    'https://dev-null.sh/@Hacksore/commits/01a14a9d-7971-73ea-8f35-135fe5149ca8?tab=replies',
    'https://example.com/@Hacksore/commits/01a14a9d-7971-73ea-8f35-135fe5149ca8',
    'https://dev-null.sh/@Hacksore',
]) {
    test(`keeps other links as URLs: ${link}`, () => {
        const markup = renderToStaticMarkup(
            <LinkifiedText text={link} mentions={null} />,
        );

        assert.ok(!markup.includes('aria-label'));
        assert.ok(markup.includes(`href="${link}"`));
    });
}

const repositoryUrl = 'https://github.com/laravel/framework';

test('shows a GitHub repository link as its owner and name', () => {
    const text = `built on ${repositoryUrl}.`;
    const markup = renderToStaticMarkup(
        <LinkifiedText text={text} mentions={null} />,
    );

    assert.ok(markup.includes(`href="${repositoryUrl}"`));
    assert.ok(markup.includes('target="_blank"'));
    assert.ok(markup.includes('rel="noopener noreferrer nofollow ugc"'));
    assert.ok(markup.includes(`title="${repositoryUrl}"`));
    assert.ok(markup.includes('aria-label="laravel/framework on GitHub"'));
    assert.equal(
        markup.replace(/<[^>]+>/g, ''),
        `built on ${repositoryUrl}laravel/framework.`,
    );
    assert.equal(
        markup
            .replace(/<span aria-hidden="true"[^>]*>.*?<\/span><\/a>/g, '')
            .replace(/<[^>]+>/g, ''),
        text,
    );
});

test('shows a GitHub repository link as its owner and name without a link when static', () => {
    const text = `built on ${repositoryUrl}.`;
    const markup = renderToStaticMarkup(
        <LinkifiedText text={text} mentions={null} isStatic />,
    );

    assert.ok(!markup.includes('<a'));
    assert.ok(markup.includes(`title="${repositoryUrl}"`));
    assert.equal(
        markup.replace(/<[^>]+>/g, ''),
        `built on ${repositoryUrl}laravel/framework.`,
    );
});

for (const [link, href] of [
    ['github.com/laravel/framework', 'https://github.com/laravel/framework'],
    [
        'https://www.github.com/laravel/framework',
        'https://www.github.com/laravel/framework',
    ],
    [
        'HTTPS://GitHub.com/laravel/framework',
        'HTTPS://GitHub.com/laravel/framework',
    ],
    [
        'http://github.com/dev-null-sh/dev-null.sh',
        'http://github.com/dev-null-sh/dev-null.sh',
    ],
]) {
    test(`shows GitHub repository links however they are written: ${link}`, () => {
        const markup = renderToStaticMarkup(
            <LinkifiedText text={link} mentions={null} />,
        );

        assert.ok(markup.includes(`href="${href}"`));
        assert.ok(markup.includes(' on GitHub"'));
        assert.equal(
            markup
                .replace(/<span aria-hidden="true"[^>]*>.*?<\/span><\/a>/g, '')
                .replace(/<[^>]+>/g, ''),
            link,
        );
    });
}

for (const [kind, path] of [
    ['issue', 'issues'],
    ['pull request', 'pull'],
]) {
    const url = `https://github.com/laravel/framework/${path}/42`;

    test(`shows a GitHub ${kind} link as its repository and number`, () => {
        const text = `fixed by ${url}.`;
        const markup = renderToStaticMarkup(
            <LinkifiedText text={text} mentions={null} />,
        );

        assert.ok(markup.includes(`href="${url}"`));
        assert.ok(markup.includes(`title="${url}"`));
        assert.ok(
            markup.includes(
                `aria-label="laravel/framework ${kind} #42 on GitHub"`,
            ),
        );
        assert.equal(
            markup.replace(/<[^>]+>/g, ''),
            `fixed by ${url}laravel/framework#42.`,
        );
        assert.equal(
            markup
                .replace(/<span aria-hidden="true"[^>]*>.*?<\/span><\/a>/g, '')
                .replace(/<[^>]+>/g, ''),
            text,
        );
    });

    test(`shows a GitHub ${kind} link as its repository and number without a link when static`, () => {
        const text = `fixed by ${url}.`;
        const markup = renderToStaticMarkup(
            <LinkifiedText text={text} mentions={null} isStatic />,
        );

        assert.ok(!markup.includes('<a'));
        assert.ok(markup.includes(`title="${url}"`));
        assert.equal(
            markup.replace(/<[^>]+>/g, ''),
            `fixed by ${url}laravel/framework#42.`,
        );
    });
}

for (const link of [
    'https://github.com/laravel',
    'https://github.com/laravel/framework/',
    'https://github.com/laravel/framework/blob/12.x/README.md',
    'https://github.com/laravel/framework/tree/12.x',
    'https://github.com/laravel/framework/releases',
    'https://github.com/laravel/framework/commit/4fa008f',
    'https://github.com/laravel/framework/pull/42/files',
    'https://github.com/laravel/framework/pull/42/',
    'https://github.com/laravel/framework/issues/0',
    'https://github.com/laravel/framework/issues/042',
    'https://github.com/laravel/framework/issues',
    'https://github.com/laravel/framework/issues/42#issuecomment-1',
    'https://github.com/laravel/framework/issues/42?page=2',
    'https://github.com/laravel/framework/discussions/42',
    'https://github.com/laravel/framework?tab=readme',
    'https://github.com/laravel/framework#readme',
    'https://github.com/features/actions',
    'https://github.com/orgs/laravel',
    'https://github.com/settings/profile',
    'https://gist.github.com/laravel/framework',
    'https://notgithub.com/laravel/framework',
]) {
    test(`keeps other GitHub links as URLs: ${link}`, () => {
        const markup = renderToStaticMarkup(
            <LinkifiedText text={link} mentions={null} />,
        );

        assert.ok(!markup.includes('aria-label'));
        assert.ok(markup.includes(`href="${link}"`));
    });
}
