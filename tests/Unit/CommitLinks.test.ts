import assert from 'node:assert/strict';
import { test } from 'node:test';
import { commitLinkTo } from '../../resources/js/lib/urls.ts';

const id = '01a16ffc-740e-7105-82dc-e3a7ed20aaaa';

await test('reads the author and the commit from a link to a dev/null commit', () => {
    assert.deepEqual(
        commitLinkTo(`https://dev-null.sh/@sulimanbenhalim/commits/${id}`),
        { handle: 'sulimanbenhalim', commit: id },
    );
    assert.deepEqual(
        commitLinkTo(
            `http://www.dev-null.sh/@nuno-maduro/commits/${id.toUpperCase()}`,
        ),
        { handle: 'nuno-maduro', commit: id },
    );
});

await test('ignores links to anything else', () => {
    for (const url of [
        'https://dev-null.sh/@sulimanbenhalim',
        `https://dev-null.sh/@sulimanbenhalim/commits/${id}/og.png`,
        `https://dev-null.sh/@sulimanbenhalim/commits/${id}?ref=log`,
        `https://dev-null.sh.example.com/@sulimanbenhalim/commits/${id}`,
        `https://github.com/@sulimanbenhalim/commits/${id}`,
        `https://dev-null.sh/@-sulimanbenhalim/commits/${id}`,
        `https://dev-null.sh/@sulimanbenhalim/commits/not-a-commit`,
    ]) {
        assert.equal(commitLinkTo(url), null, url);
    }
});
