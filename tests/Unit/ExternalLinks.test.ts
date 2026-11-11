import assert from 'node:assert/strict';
import { test } from 'node:test';
import {
    parseExternalLink,
    readTrustedDomains,
    trustDomain,
    writeTrustedDomains,
} from '../../resources/js/lib/external-links.ts';

function memoryStorage(initial: Record<string, string> = {}): Storage {
    const store = new Map(Object.entries(initial));

    return {
        getItem: (key: string) => store.get(key) ?? null,
        setItem: (key: string, value: string) => {
            store.set(key, value);
        },
    } as Storage;
}

await test('flags outside links and passes internal, relative and invalid ones', () => {
    assert.deepEqual(
        parseExternalLink('https://evil.example/x', 'dev-null.sh'),
        {
            url: 'https://evil.example/x',
            domain: 'evil.example',
        },
    );
    assert.equal(
        parseExternalLink('https://dev-null.sh/commits', 'dev-null.sh'),
        null,
    );
    assert.equal(
        parseExternalLink('https://dev-null.sh.evil.example/', 'dev-null.sh')
            ?.domain,
        'dev-null.sh.evil.example',
    );
    assert.equal(parseExternalLink('/commits', 'dev-null.sh'), null);
    assert.equal(
        parseExternalLink('mailto:someone@example.com', 'dev-null.sh'),
        null,
    );
    assert.equal(parseExternalLink('https://', 'dev-null.sh'), null);
    assert.equal(
        parseExternalLink('  HTTPS://EXAMPLE.COM/x  ', 'dev-null.sh')?.domain,
        'example.com',
    );
});

await test('trusts a domain once and remembers the list', () => {
    assert.deepEqual(trustDomain([], 'Example.com'), ['Example.com']);
    assert.deepEqual(trustDomain(['example.com'], 'EXAMPLE.COM'), [
        'example.com',
    ]);
    assert.deepEqual(trustDomain(['a.example'], 'b.example'), [
        'a.example',
        'b.example',
    ]);
});

await test('round-trips trusted domains and ignores corrupt storage', () => {
    const storage = memoryStorage();

    assert.deepEqual(readTrustedDomains(storage), []);

    writeTrustedDomains(storage, ['example.com']);
    assert.deepEqual(readTrustedDomains(storage), ['example.com']);
    assert.deepEqual(
        readTrustedDomains(
            memoryStorage({ 'trusted-external-domains': 'nope[' }),
        ),
        [],
    );
    assert.deepEqual(readTrustedDomains(undefined), []);
});
