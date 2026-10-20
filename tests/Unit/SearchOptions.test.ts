import assert from 'node:assert/strict';
import { test } from 'node:test';
import {
    activeIndexFor,
    optionsFor,
} from '../../resources/js/lib/search-options.ts';
import type { Commit, User } from '../../resources/js/types';

const developer = (id: string) => ({ user: { id } as User });
const commit = (id: string) => ({ id }) as Commit;
const developers = [developer('nuno'), developer('taylor')];

await test('a picked developer stays highlighted when commit results land in front of it', () => {
    const before = optionsFor('topic', developers, [], 'redis caching');
    assert.equal(before[1].id, 'developer-nuno');

    const after = optionsFor(
        'topic',
        developers,
        [commit('first'), commit('second')],
        'redis caching',
    );

    assert.equal(after[1].id, 'commit-second');
    assert.equal(after[activeIndexFor(after, 'developer-nuno')].id, 'developer-nuno');
});

await test('the first option is highlighted when none is picked or the picked one is gone', () => {
    const options = optionsFor('either', developers, [commit('first')], 'nuno');

    assert.equal(activeIndexFor(options, null), 0);
    assert.equal(activeIndexFor(options, 'commit-gone'), 0);
    assert.equal(activeIndexFor(options, 'all'), 3);
});
