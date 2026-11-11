import { useEffect, useRef, useState } from 'react';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    parseExternalLink,
    readTrustedDomains,
    trustDomain,
    writeTrustedDomains,
    type ExternalLink,
} from '@/lib/external-links';

function trustedStorage(): Storage | undefined {
    if (typeof window === 'undefined') {
        return undefined;
    }

    try {
        return window.localStorage;
    } catch {
        // Blocked storage must not break the warning flow.
        return undefined;
    }
}

/**
 * Warns before following external links, since labels and shorteners can disguise where a link
 * lands. Trusted domains are remembered in local storage and open without asking.
 */
export function ExternalLinkGuard() {
    const [pending, setPending] = useState<{
        link: ExternalLink;
        anchor: HTMLAnchorElement;
    } | null>(null);
    const [shownLink, setShownLink] = useState<ExternalLink | null>(null);
    const [trustChecked, setTrustChecked] = useState(false);
    // One-use pass for the anchor the visitor just confirmed, consumed on that click.
    const confirmedAnchor = useRef<HTMLAnchorElement | null>(null);

    useEffect(() => {
        const onClick = (event: MouseEvent): void => {
            if (
                event.defaultPrevented ||
                event.button !== 0 ||
                event.metaKey ||
                event.ctrlKey ||
                event.shiftKey ||
                event.altKey
            ) {
                return;
            }

            if (!(event.target instanceof Element)) {
                return;
            }

            const anchor = event.target.closest('a[href]');

            if (!(anchor instanceof HTMLAnchorElement)) {
                return;
            }

            if (confirmedAnchor.current === anchor) {
                confirmedAnchor.current = null;

                return;
            }

            const external = parseExternalLink(
                anchor.getAttribute('href') ?? '',
                window.location.href,
            );

            if (external === null) {
                return;
            }

            const trusted = readTrustedDomains(trustedStorage()).some(
                (domain) =>
                    domain.toLowerCase() === external.domain.toLowerCase(),
            );

            if (trusted) {
                return;
            }

            event.preventDefault();
            setTrustChecked(false);
            setShownLink(external);
            setPending({ link: external, anchor });
        };

        document.addEventListener('click', onClick, true);

        return () => document.removeEventListener('click', onClick, true);
    }, []);

    const stay = (): void => setPending(null);

    const leave = (): void => {
        if (pending === null) {
            return;
        }

        if (trustChecked) {
            writeTrustedDomains(
                trustedStorage(),
                trustDomain(
                    readTrustedDomains(trustedStorage()),
                    pending.link.domain,
                ),
            );
        }

        const { link, anchor } = pending;
        setPending(null);
        confirmedAnchor.current = anchor;

        if (anchor.isConnected) {
            anchor.click();
        } else {
            confirmedAnchor.current = null;
            window.open(link.url, '_blank', 'noopener,noreferrer');
        }
    };

    return (
        <Dialog
            open={pending !== null}
            onOpenChange={(open) => {
                if (!open) {
                    stay();
                }
            }}
        >
            <DialogContent data-test="external-link-guard">
                <DialogHeader>
                    <DialogTitle>Head to {shownLink?.domain}?</DialogTitle>
                    <DialogDescription>
                        This link takes you to an external site. Addresses can
                        be disguised, so check it before you continue.
                    </DialogDescription>
                </DialogHeader>
                <p className="rounded-md bg-muted px-3 py-2 font-mono text-xs wrap-anywhere">
                    {shownLink?.url}
                </p>
                <label className="flex cursor-pointer items-center gap-2 text-sm">
                    <Checkbox
                        checked={trustChecked}
                        onCheckedChange={(checked) =>
                            setTrustChecked(checked === true)
                        }
                        data-test="external-link-trust"
                    />
                    Don&apos;t warn me about links to {shownLink?.domain} again
                </label>
                <DialogFooter>
                    <Button
                        variant="outline"
                        onClick={stay}
                        data-test="external-link-stay"
                    >
                        Stay here
                    </Button>
                    <Button onClick={leave} data-test="external-link-leave">
                        Visit site
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
