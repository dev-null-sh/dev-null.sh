// External links open a leave-site warning unless their domain is trusted.

export type ExternalLink = {
    url: string;
    domain: string;
};

/**
 * The link details when href points outside host, null for internal, relative and invalid links.
 */
export function parseExternalLink(
    href: string,
    host: string,
): ExternalLink | null {
    if (!/^\s*https?:\/\//i.test(href)) {
        return null;
    }

    let url: URL | null = null;

    try {
        url = new URL(href.trim());
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
