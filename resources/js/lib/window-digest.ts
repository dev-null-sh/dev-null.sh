/**
 * Names a loaded window of a log by its members, as the server names the same window: the SHA-256 of their sorted ids,
 * so a page can tell its window changed even when as many commits left it as arrived. Null where the browser can't
 * hash, which callers treat as changed.
 */
export async function windowDigest(ids: string[]): Promise<string | null> {
    if (globalThis.crypto?.subtle === undefined) {
        return null;
    }

    const hash = await globalThis.crypto.subtle.digest(
        'SHA-256',
        new TextEncoder().encode([...ids].sort().join(',')),
    );

    return [...new Uint8Array(hash)]
        .map((byte) => byte.toString(16).padStart(2, '0'))
        .join('');
}
