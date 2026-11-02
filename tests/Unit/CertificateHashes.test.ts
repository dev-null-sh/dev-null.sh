import assert from 'node:assert/strict';
import { test } from 'node:test';
import { withCertificateHashes } from '../../resources/js/lib/certificate-hashes.ts';

const fingerprints = [
    'E7568FE6E12331F4FD7DE61DA4C7559D0F8EB192A218D4741A3BA75184333838',
    '98B896625C0C230ADEEC3EB2CF6AAD5001ECF9156A68644E82374CE5833BC25B',
    '42C4ACCD12C4F59597925E1A08A7A90102608543809308D50EC427E118BA1493',
    '432EC25F0D28DE9006D97BF752F17BBB2CD41F669933FCB072D9A1148D0261F3',
    'CB6E15165AC802E9B3F993F16C25D41F4E81C21EBDE71AE1B012AC00BF9C7C3F',
    '0164D7CEAE5BB909C272AF151FF44B1177AE9B47D9424B94F1D9558C30934D19',
    '9E39DE2881273ACC225A149C72BA3E0F51424963669AB633EE1DC7DF5DB067B4',
    '4B2460A466F95FD40113BCDCCA2CEB29B3B093921DE45474DA7DF48874C630B1',
];

void test('decorates all eight compromised fingerprints without changing their text or case', () => {
    for (const fingerprint of fingerprints) {
        for (const hash of [fingerprint, fingerprint.toLowerCase()]) {
            assert.equal(
                withCertificateHashes(`SHA-256: ${hash}, ${hash}`),
                `SHA-256: <span class="certificate-hash"><span>${hash}</span></span>, <span class="certificate-hash"><span>${hash}</span></span>`,
            );
        }
    }
});

void test('leaves unrelated, partial and longer hashes untouched', () => {
    const hash = fingerprints[0];
    const text = `${hash.slice(0, -1)} ${hash.slice(0, -1)}9 0${hash} ${hash}f`;

    assert.equal(withCertificateHashes(text), text);
});

void test('preserves syntax highlighting, escaped code and HTML attributes', () => {
    const hash = fingerprints[0];
    const html = `<span class="hl-string" title="${hash}">&quot;${hash}&quot;</span> &lt;script&gt;`;

    assert.equal(
        withCertificateHashes(html),
        `<span class="hl-string" title="${hash}">&quot;<span class="certificate-hash"><span>${hash}</span></span>&quot;</span> &lt;script&gt;`,
    );
});
