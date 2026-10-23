/**
 * What the badge on the tab's icon reads: a single digit stays legible at 16px, so more than nine reads "9+".
 */
export function faviconBadgeLabel(count: number): string | null {
    if (count <= 0) {
        return null;
    }

    return count > 9 ? '9+' : String(count);
}

/**
 * The favicon with a badge in its top-right corner, drawn in the icon's own coordinates.
 */
export function withFaviconBadge(svg: string, count: number): string {
    const label = faviconBadgeLabel(count);

    if (label === null) {
        return svg;
    }

    const badge =
        `<circle cx="33.5" cy="15" r="11.5" fill="#e5484d" stroke="#fff" stroke-width="2.5"/>` +
        `<text x="33.5" y="15" fill="#fff" font-family="system-ui,sans-serif" font-size="${label.length > 1 ? 13.5 : 17}" ` +
        `font-weight="700" text-anchor="middle" dominant-baseline="central">${label}</text>`;

    return svg.replace('</svg>', `${badge}</svg>`);
}

let original: { href: string; svg: Promise<string> } | null = null;
let latest = 0;

/**
 * Shows the unread count on the tab's SVG icon, and puts the plain icon back once everything is read.
 */
export async function showFaviconBadge(count: number): Promise<void> {
    const link = document.querySelector<HTMLLinkElement>(
        'link[rel="icon"][type="image/svg+xml"]',
    );

    if (link === null) {
        return;
    }

    latest = count;
    original ??= {
        href: link.href,
        svg: fetch(link.href).then((response) => response.text()),
    };

    if (faviconBadgeLabel(count) === null) {
        link.href = original.href;

        return;
    }

    const svg = await original.svg.catch(() => null);

    // Counts can change while the icon loads; only the newest one is drawn.
    if (svg === null || count !== latest) {
        return;
    }

    link.href = `data:image/svg+xml,${encodeURIComponent(withFaviconBadge(svg, count))}`;
}
