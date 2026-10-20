import type { Secret } from '@sanity-labs/secret-scan';
import { redact, scan } from '@sanity-labs/secret-scan';

/** Once GitHub is mentioned, any 40-character hex string reads as a GitHub OAuth secret, and every commit hash in a GitHub link is one. A hash that's a segment of a link's path is an address, not a secret. A secret in a link sits in its query or before the host, which this doesn't skip. */
const isHashInLinkPath = (body: string, secret: Secret): boolean => {
    if (secret.rule !== 'github_oauth2-2') {
        return false;
    }

    const linkSoFar = /\S*$/.exec(body.slice(0, secret.start))?.[0] ?? '';

    return /(?:^|[^\w.-])(?:https?:\/\/)?[\w-]+(?:\.[\w-]+)+(?::\d+)?\/[^\s?#@]*\/$/.test(
        linkSoFar,
    );
};

export function findSecrets(body: string): Secret[] {
    return scan(body).filter((secret) => !isHashInLinkPath(body, secret));
}

export function removeSecrets(body: string): string {
    return redact(body, (secret) =>
        isHashInLinkPath(body, secret) ? secret.text : '[REDACTED]',
    );
}

export function stashableBody(body: string): string {
    return findSecrets(body).length > 0 ? '' : body;
}
