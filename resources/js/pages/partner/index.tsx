import { Head, Link } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import {
    DocumentList,
    DocumentPage,
    DocumentSection,
} from '@/components/document-page';
import { GitHubIcon } from '@/components/github-icon';
import { Button } from '@/components/ui/button';
import { UserPreview } from '@/components/user-preview';
import { WordmarkText } from '@/components/wordmark-text';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import { show } from '@/routes/user';

const sponsorsUrl = 'https://github.com/sponsors/dev-null-sh';

const tierNames = {
    50000: 'Distinguished Partners',
    20000: 'Principal Partners',
    5000: 'Staff Partners',
    500: 'Senior Partners',
    0: 'Junior Partners',
} as const;

type Partner = { login: string; handle: string | null };

type Tier = { amount: keyof typeof tierNames; partners: Partner[] };

const avatar =
    'block size-12 rounded-full ring-4 ring-background transition-transform hover:-translate-y-1';

function tierLine(amount: Tier['amount']) {
    return amount === 0
        ? 'Up to $499 from each'
        : `$${amount.toLocaleString('en-US')} or more from each`;
}

function PartnerAvatar({ login }: { login: string }) {
    return (
        <img
            src={`https://github.com/${login}.png?size=96`}
            alt=""
            width={48}
            height={48}
            loading="lazy"
            className="size-full rounded-full"
        />
    );
}

function PartnerLink({ partner }: { partner: Partner }) {
    if (partner.handle === null) {
        return (
            <Link
                prefetch={false}
                href={show({ handle: partner.login })}
                aria-label={`@${partner.login} on /dev/null`}
                className={cn(avatar, 'bg-muted')}
            >
                <PartnerAvatar login={partner.login} />
            </Link>
        );
    }

    return (
        <UserPreview user={{ handle: partner.handle }} className="block">
            <Link
                prefetch
                href={show({ handle: partner.handle })}
                aria-label={`@${partner.handle} on /dev/null`}
                className={cn(avatar, 'bg-muted')}
            >
                <PartnerAvatar login={partner.login} />
            </Link>
        </UserPreview>
    );
}

function TierGroup({ tier }: { tier: Tier }) {
    const layer = 'relative shrink-0 hover:z-50! focus-within:z-50!';

    return (
        <section
            aria-labelledby={`tier-${tier.amount}-title`}
            className={cn('space-y-1', tier.amount === 0 && 'sm:col-span-2')}
        >
            <h2
                id={`tier-${tier.amount}-title`}
                className="text-lg font-semibold tracking-tight"
            >
                {tierNames[tier.amount]}
            </h2>
            <p className="font-mono text-[13px] text-muted-foreground tabular-nums">
                {tierLine(tier.amount)}
            </p>
            <ul className="flex flex-wrap pt-3 pl-2 [&>li]:-ml-2">
                {tier.partners.map((partner, index) => (
                    <li
                        key={partner.login}
                        className={layer}
                        style={{ zIndex: tier.partners.length - index + 1 }}
                    >
                        <PartnerLink partner={partner} />
                    </li>
                ))}
                <li className={layer} style={{ zIndex: 1 }}>
                    <a
                        href={sponsorsUrl}
                        target="_blank"
                        rel="noopener noreferrer"
                        title="Your face here"
                        aria-label={`Become one of the ${tierNames[tier.amount]} on GitHub`}
                        className={cn(
                            avatar,
                            'flex items-center justify-center border border-dashed border-rail bg-background text-muted-foreground hover:border-input hover:text-foreground',
                        )}
                    >
                        <Plus className="size-4" />
                    </a>
                </li>
            </ul>
        </section>
    );
}

export default function Index({ tiers }: { tiers: Tier[] | null }) {
    return (
        <>
            <Head title="Partners" />

            <DocumentPage title="Partners">
                <section className="space-y-3 px-4 py-6 text-[15px] text-pretty sm:px-6">
                    <p>
                        <WordmarkText>/dev/null</WordmarkText> is a free, open
                        source social network for developers, built by the
                        community. It will never have ads.
                    </p>
                    <p>
                        Running it still costs money every month. The people and
                        companies on this page sponsor{' '}
                        <WordmarkText>/dev/null</WordmarkText> on GitHub, and
                        they're the reason it keeps running. Thank you.
                    </p>
                    <Button asChild className="no-underline!">
                        <a
                            href={sponsorsUrl}
                            target="_blank"
                            rel="noopener noreferrer"
                        >
                            <GitHubIcon className="size-4" />
                            Sponsor on GitHub
                        </a>
                    </Button>
                </section>

                {tiers === null ? (
                    <DocumentSection id="partners" title="Partners">
                        <p className="text-muted-foreground">
                            GitHub couldn't be reached. Try again in a minute.
                        </p>
                    </DocumentSection>
                ) : (
                    <div className="grid gap-x-8 gap-y-10 px-4 py-6 sm:grid-cols-2 sm:px-6">
                        {tiers.map((tier) => (
                            <TierGroup key={tier.amount} tier={tier} />
                        ))}
                    </div>
                )}

                <DocumentSection id="money" title="Where the money goes">
                    <DocumentList
                        items={[
                            'First, the bills: hosting, and the AI behind moderation, search, translation and Tom.',
                            <>
                                Then, once the bills are covered, getting the
                                team together to build more of{' '}
                                <WordmarkText>/dev/null</WordmarkText>.
                            </>,
                        ]}
                    />
                </DocumentSection>

                <DocumentSection id="perks" title="What partners get">
                    <p>
                        Your name on this page, in the biggest tier that the
                        total you sponsored over time reaches.
                    </p>
                    <p>
                        That's it. Sponsoring doesn't buy promoted commits, a
                        badge in the logs, or any say in what trends. If you
                        sponsor privately on GitHub, you won't be listed.
                    </p>
                </DocumentSection>
            </DocumentPage>
        </>
    );
}

Index.layout = AppLayout;
