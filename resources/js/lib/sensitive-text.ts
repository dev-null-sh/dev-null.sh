import { redact, scan } from '@sanity-labs/secret-scan';

export { scan as findSecrets };

export function removeSecrets(body: string): string {
    return redact(body, () => '[REDACTED]');
}

export function stashableBody(body: string | string[]): string {
    if (Array.isArray(body)) {
        return body.some((part) => scan(part).length > 0)
            ? ''
            : JSON.stringify(body);
    }

    return scan(body).length > 0 ? '' : body;
}
