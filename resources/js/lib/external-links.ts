// External links open a leave-site warning unless their domain is trusted.

export type ExternalLink = {
    url: string;
    domain: string;
};

/**
 * The link details when href points outside the current site, null for internal, relative and
 * non-HTTP(S) links. Protocol-relative links resolve against the page, so //evil.example counts.
 */
export function parseExternalLink(
    href: string,
    base: string,
): ExternalLink | null {
    let url: URL;

    try {
        url = new URL(href, base);
    } catch {
        return null;
    }

    if (url.protocol !== 'http:' && url.protocol !== 'https:') {
        return null;
    }

    let host: string;

    try {
        host = new URL(base).host;
    } catch {
        return null;
    }

    if (url.host.toLowerCase() === host.toLowerCase()) {
        return null;
    }

    return { url: url.href, domain: url.host };
}

const trustedDomainsKey = 'trusted-external-domains';

export function readTrustedDomains(
    storage: Pick<Storage, 'getItem'> | undefined,
): string[] {
    try {
        const raw = storage?.getItem(trustedDomainsKey);

        if (!raw) {
            return [];
        }

        const parsed: unknown = JSON.parse(raw);

        return Array.isArray(parsed)
            ? parsed.filter(
                  (domain): domain is string => typeof domain === 'string',
              )
            : [];
    } catch {
        return [];
    }
}

export function trustDomain(domains: string[], domain: string): string[] {
    const normalized = domain.toLowerCase();

    return domains.some((existing) => existing.toLowerCase() === normalized)
        ? domains
        : [...domains, domain];
}

export function writeTrustedDomains(
    storage: Pick<Storage, 'setItem'> | undefined,
    domains: string[],
): void {
    try {
        storage?.setItem(trustedDomainsKey, JSON.stringify(domains));
    } catch {
        // Private browsing and disabled storage just skip persisting.
    }
}
