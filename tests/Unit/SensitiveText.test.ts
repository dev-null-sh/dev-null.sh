import assert from 'node:assert/strict';
import { test } from 'node:test';
import {
    findSecrets,
    removeSecrets,
    stashableBody,
} from '../../resources/js/lib/sensitive-text.ts';

const githubToken = 'ghp_' + 'Ab3dEf6hIj9kLm2nOp5qRs8tUv1wXy4zABCD';

await test('finds a pasted token and returns the exact selection after Unicode text', () => {
    const body = `My key 🔑: ${githubToken}`;
    const findings = findSecrets(body);
    assert.equal(findings.length, 1);
    assert.equal(body.slice(findings[0].start, findings[0].end), githubToken);
    assert.equal(stashableBody(body), '');
});

await test('removes multiple secrets without changing surrounding prose', () => {
    const body = `Token ${githubToken}; database postgres://demo:LocalOnly7z9@localhost/demo`;
    const cleaned = removeSecrets(body);
    assert.equal(cleaned, 'Token [REDACTED]; database [REDACTED]');
    assert.equal(findSecrets(cleaned).length, 0);
    assert.equal(stashableBody(cleaned), cleaned);
});

await test('detects a complete private key block', () => {
    const body =
        '-----BEGIN PRIVATE KEY-----\nAbCdEfGhIjKlMnOp1234567890\n-----END PRIVATE KEY-----';
    assert.ok(findSecrets(body).length > 0);
    assert.equal(stashableBody(body), '');
});

await test('preserves ordinary code and obvious placeholder examples', () => {
    for (const body of [
        '',
        'const id = "a1b2c3d4"; // hello world',
        'ghp_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx',
    ]) {
        assert.deepEqual(findSecrets(body), []);
        assert.equal(removeSecrets(body), body);
        assert.equal(stashableBody(body), body);
    }
});

await test('leaves the commit hash in a GitHub link alone', () => {
    const hash = '3f2a9c1e8b7d6a5f4e3d2c1b0a9f8e7d6c5b4a39';
    for (const body of [
        `Fixed in https://github.com/nunomaduro/dev-null.sh/commit/${hash}`,
        `[the fix](https://github.com/nunomaduro/dev-null.sh/blob/${hash}/app/Models/User.php#L12)`,
    ]) {
        assert.deepEqual(findSecrets(body), []);
        assert.equal(removeSecrets(body), body);
        assert.equal(stashableBody(body), body);
    }
});

await test('still finds a GitHub secret outside a link path, even beside a commit link', () => {
    const hash = '3f2a9c1e8b7d6a5f4e3d2c1b0a9f8e7d6c5b4a39';
    const secret = '4e1b8d2c7a6f5e4d3c2b1a0f9e8d7c6b5a4f3e21';
    assert.equal(
        removeSecrets(`My GitHub client secret: ${secret}`),
        'My GitHub client secret: [REDACTED]',
    );
    assert.equal(
        removeSecrets(`git clone https://${secret}@github.com/acme/app.git`),
        'git clone https://[REDACTED]@github.com/acme/app.git',
    );
    assert.equal(
        removeSecrets(`See https://github.com/acme/app/commit/${hash}, secret ${secret}`),
        `See https://github.com/acme/app/commit/${hash}, secret [REDACTED]`,
    );
});
