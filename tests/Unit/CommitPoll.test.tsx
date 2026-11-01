/// <reference types="node" />
import assert from 'node:assert/strict';
import { App } from '@inertiajs/react';
import type { ComponentProps, ReactElement } from 'react';
import { renderToStaticMarkup } from 'react-dom/server';
import { test } from 'vite-plus/test';
import { CommitPoll } from '../../resources/js/components/commit-poll';
import { TooltipProvider } from '../../resources/js/components/ui/tooltip';
import type { Poll } from '../../resources/js/types';

const mentionedId = '019a2b3c-4d5e-7f60-8a9b-0c1d2e3f4a5b';
const commit = {
    id: '019a2b3c-0000-7000-8000-000000000001',
    author: { id: '019a2b3c-0000-7000-8000-000000000002' },
    status: 'approved' as const,
    mentions: { alice: mentionedId },
};
const labels = [
    'Bun :rocket: https://bun.sh',
    'https://github.com/laravel/framework',
    'pipe to /dev/null with `rm -rf`',
    '@alice or @bob',
];

function pollWith(results: boolean): Poll {
    return {
        id: '019a2b3c-0000-7000-8000-000000000003',
        closes_at: '2999-01-01T00:00:00Z',
        is_closed: false,
        has_voted: results,
        total_votes: results ? 4 : null,
        options: labels.map((label, index) => ({
            id: String(index),
            label,
            votes_count: results ? 1 : null,
            voted: false,
        })),
    };
}

function renderPage(
    content: ReactElement,
    userId: string | null = null,
): string {
    return renderToStaticMarkup(
        <App
            initialComponent={() => (
                <TooltipProvider>{content}</TooltipProvider>
            )}
            initialPage={
                {
                    component: 'Commit',
                    url: '/',
                    version: null,
                    props: {
                        errors: {},
                        auth: {
                            user: userId === null ? null : { id: userId },
                            pasteis: null,
                        },
                    },
                    clearHistory: false,
                    encryptHistory: false,
                } as unknown as ComponentProps<typeof App>['initialPage']
            }
        />,
    );
}

function openResults(): Poll {
    return {
        ...pollWith(true),
        has_voted: false,
        options: pollWith(true).options.map((option) => ({
            ...option,
            voted: false,
        })),
    };
}

for (const results of [false, true]) {
    test(`renders poll options like a commit body in the ${results ? 'results' : 'vote'} view`, () => {
        const markup = renderPage(
            <CommitPoll commit={commit} poll={pollWith(results)} />,
        );

        assert.ok(markup.includes('Bun 🚀'));
        assert.ok(markup.includes('href="https://bun.sh"'));
        assert.ok(markup.includes('aria-label="laravel/framework on GitHub"'));
        assert.ok(markup.includes('title="/dev/null"'));
        assert.match(markup, /<code[^>]*>rm -rf<\/code>/);
        assert.ok(markup.includes(`href="/mentions/${mentionedId}"`));
        assert.equal(markup.match(/data-test="mention"/g)?.length, 1);
        assert.ok(markup.includes('or @bob'));
        assert.equal(markup.includes('View results'), false);
    });
}

for (const results of [false, true]) {
    test(`renders poll options without links in a static ${results ? 'results' : 'vote'} view`, () => {
        const markup = renderPage(
            <CommitPoll commit={commit} poll={pollWith(results)} isStatic />,
        );

        assert.ok(!markup.includes('<a'));
        assert.ok(markup.includes('Bun 🚀'));
        assert.ok(
            markup.includes('title="https://github.com/laravel/framework"'),
        );
        assert.ok(markup.includes('title="/dev/null"'));
        assert.match(markup, /<code[^>]*>rm -rf<\/code>/);
        assert.equal(markup.includes('View results'), false);
    });
}

test('offers a focusable view results button before a signed-in reader votes', () => {
    const markup = renderPage(
        <CommitPoll commit={commit} poll={pollWith(false)} />,
        '019a2b3c-0000-7000-8000-000000000099',
    );
    const button = markup.match(/<button[^>]*>View results<\/button>/);

    assert.ok(button);
    assert.match(button[0], /type="button"/);
    assert.match(button[0], /data-test="poll-view-results"/);
    assert.match(button[0], /focus-visible:ring-\[3px\]/);
    assert.match(button[0], /focus-visible:ring-ring\/50/);
    assert.doesNotMatch(button[0], /\sdisabled[\s=>]/);
    assert.match(markup, /data-test="poll-vote-button"/);
    assert.equal(markup.includes('data-test="poll-summary"'), false);
});

test('shows counts and keeps voting after results are visible', () => {
    const markup = renderPage(
        <CommitPoll commit={commit} poll={openResults()} />,
        '019a2b3c-0000-7000-8000-000000000099',
    );

    assert.equal(markup.includes('View results'), false);
    assert.match(markup, /data-test="poll-vote-button"/);
    assert.match(markup, /type="radio"/);
    assert.match(markup, /data-test="poll-summary"/);
    assert.ok(markup.includes('25%'));
    assert.ok(markup.includes('4 votes'));
});

test('hides view results from the author and from a closed poll', () => {
    const authorMarkup = renderPage(
        <CommitPoll commit={commit} poll={openResults()} />,
        commit.user_id,
    );

    assert.equal(authorMarkup.includes('View results'), false);
    assert.equal(authorMarkup.includes('data-test="poll-vote-button"'), false);
    assert.ok(authorMarkup.includes('data-test="poll-summary"'));

    const closedMarkup = renderPage(
        <CommitPoll
            commit={commit}
            poll={{
                ...openResults(),
                is_closed: true,
                closes_at: '2000-01-01T00:00:00Z',
            }}
        />,
        '019a2b3c-0000-7000-8000-000000000099',
    );

    assert.equal(closedMarkup.includes('View results'), false);
    assert.equal(closedMarkup.includes('data-test="poll-vote-button"'), false);
});

test('shortens a big poll total but keeps the exact total on hover and for screen readers', () => {
    const poll = {
        ...pollWith(true),
        total_votes: 10_250,
        options: pollWith(true).options.map((option, index) => ({
            ...option,
            votes_count: index === 0 ? 10_247 : 1,
        })),
    };
    const markup = renderPage(<CommitPoll commit={commit} poll={poll} />);
    const summary = markup.match(
        /<p[^>]*data-test="poll-summary"[^>]*>(.*?)<\/p>/,
    )?.[1];

    assert.ok(summary);
    assert.match(
        summary,
        /<span aria-hidden="true" title="10,250 votes">10\.2k votes<\/span>/,
    );
    assert.match(summary, /<span class="sr-only">10,250 votes<\/span>/);
});

test('shows a small poll total in full without a hover', () => {
    const markup = renderPage(
        <CommitPoll commit={commit} poll={pollWith(true)} />,
    );
    const summary = markup.match(
        /<p[^>]*data-test="poll-summary"[^>]*>(.*?)<\/p>/,
    )?.[1];

    assert.ok(summary);
    assert.match(summary, /<span aria-hidden="true">4 votes<\/span>/);
    assert.match(summary, /<span class="sr-only">4 votes<\/span>/);
});
