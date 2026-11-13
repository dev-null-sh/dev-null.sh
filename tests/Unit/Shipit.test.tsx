import assert from 'node:assert/strict';
import { renderToStaticMarkup } from 'react-dom/server';
import { test } from 'vite-plus/test';
import { LaunchShip } from '../../resources/js/components/launch-ship';
import { shipitDraft } from '../../resources/js/lib/shipit';

test('recognizes a standalone shipit command and preserves the draft before it', () => {
    assert.equal(shipitDraft('/shipit'), '');
    assert.equal(shipitDraft('Fix the typo.\n/shipit'), 'Fix the typo.');
    assert.equal(
        shipitDraft('  Keep this spacing.  \n\n/shipit '),
        '  Keep this spacing.  \n',
    );
    assert.equal(shipitDraft('A mobile draft\n/shipit\n'), 'A mobile draft');
    assert.equal(shipitDraft('Windows draft\r\n/shipit'), 'Windows draft');
});

test('leaves ordinary prose and commands mentioned inside code alone', () => {
    for (const body of [
        'Try /shipit',
        '/shipit later',
        '/shipit-extra',
        '`/shipit`',
        '```text\n/shipit',
        '```text\n/shipit\n```',
        '> /shipit',
        '',
    ]) {
        assert.equal(shipitDraft(body), null, body);
    }
});

test('allows a command after a closed code block', () => {
    const draft = '```sh\necho hello\n```';
    assert.equal(shipitDraft(`${draft}\n/shipit`), draft);
});

test('renders at most sixteen passengers on decks and counts everyone else aboard', () => {
    const passengers = Array.from({ length: 1000 }, (_, index) => ({
        id: `p${index}`,
        name: `Developer ${index}`,
        handle: `dev${index}`,
        avatar_url: null,
    }));
    const markup = renderToStaticMarkup(
        <LaunchShip count={1000} passengers={passengers} />,
    );
    assert.equal(
        (markup.match(/data-test="launch-passenger"/g) ?? []).length,
        16,
    );
    assert.ok(markup.includes('+984'));
    assert.ok(markup.includes('launch-overloaded'));
    assert.ok(markup.includes('Developers aboard'));
    assert.ok(markup.includes('launch-person'));
    assert.ok(markup.includes('launch-passenger-deck'));
    assert.ok(!markup.includes('crate'));
    assert.ok(!markup.includes('launch-cargo'));
});

test('an empty launch still renders a ship without any invented passengers', () => {
    const markup = renderToStaticMarkup(
        <LaunchShip count={0} passengers={[]} />,
    );
    assert.ok(markup.includes('shipped it'));
    assert.ok(!markup.includes('data-test="launch-passenger"'));
    assert.ok(!markup.includes('launch-overloaded'));
});

test('the creator is displayed as captain without becoming a passenger', () => {
    const markup = renderToStaticMarkup(
        <LaunchShip
            count={0}
            passengers={[]}
            captain={{ name: 'Creator', handle: 'creator', avatar_url: null }}
        />,
    );
    assert.ok(markup.includes('Captain @creator'));
    assert.ok(markup.includes('launch-captain-hat'));
    assert.ok(markup.includes('data-passengers="0"'));
    assert.ok(!markup.includes('data-test="launch-passenger"'));
});
