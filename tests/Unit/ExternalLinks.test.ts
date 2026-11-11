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
    const base = 'https://dev-null.sh/commits?page=2';

    assert.deepEqual(parseExternalLink('https://evil.example/x', base), {
        url: 'https://evil.example/x',
        domain: 'evil.example',
    });
    assert.equal(parseExternalLink('https://dev-null.sh/commits', base), null);
    assert.equal(
        parseExternalLink('https://dev-null.sh.evil.example/', base)?.domain,
        'dev-null.sh.evil.example',
    );
    assert.equal(parseExternalLink('/commits', base), null);
    assert.equal(
        parseExternalLink('//evil.example/x', base)?.domain,
        'evil.example',
    );
    assert.equal(parseExternalLink('//dev-null.sh/x', base), null);
    assert.equal(parseExternalLink('mailto:someone@example.com', base), null);
    assert.equal(parseExternalLink('https://', base), null);
    assert.equal(parseExternalLink('javascript:alert(1)', base), null);
    assert.equal(
        parseExternalLink('  HTTPS://EXAMPLE.COM/x  ', base)?.domain,
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
