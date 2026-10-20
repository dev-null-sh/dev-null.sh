import assert from 'node:assert/strict';
import { test } from 'node:test';
import {
    issueReferenceAt,
    shouldAutoLinkIssueReference,
} from '../../resources/js/lib/issue-references.ts';

test('finds a #number at the caret', () => {
    assert.deepEqual(issueReferenceAt('see #722', 8), {
        start: 4,
        query: '722',
    });
});

test('ignores a bare hash and non-digits', () => {
    assert.equal(issueReferenceAt('see #', 5), null);
    assert.equal(issueReferenceAt('see #abc', 8), null);
});

test('ignores hashes inside words or paths', () => {
    assert.equal(issueReferenceAt('foo#722', 7), null);
    assert.equal(issueReferenceAt('owner/repo#722', 14), null);
});

test('auto-links only a single exact number match', () => {
    const reference = {
        number: 722,
        title: 'Automatically link PRs and issues',
        url: 'https://github.com/nunomaduro/dev-null.sh/issues/722',
        kind: 'issue' as const,
    };

    assert.equal(shouldAutoLinkIssueReference('722', [reference]), reference);
    assert.equal(shouldAutoLinkIssueReference('72', [reference]), null);
    assert.equal(
        shouldAutoLinkIssueReference('722', [
            reference,
            { ...reference, number: 7220 },
        ]),
        null,
    );
});
