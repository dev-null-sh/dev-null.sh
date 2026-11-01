import { Head } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import {
    DocumentList,
    DocumentPage,
    DocumentSection,
} from '@/components/document-page';
import { GitHubIcon } from '@/components/github-icon';
import { Button } from '@/components/ui/button';
import { WordmarkText } from '@/components/wordmark-text';
import AppLayout from '@/layouts/app-layout';

const sponsorsUrl = 'https://github.com/sponsors/dev-null-sh';

function OpenSpots() {
    return (
        <ul aria-hidden className="grid grid-cols-4 gap-2">
            {Array.from({ length: 8 }, (_, index) => (
                <li key={index}>
                    <a
                        href={sponsorsUrl}
                        target="_blank"
                        rel="noopener noreferrer"
                        tabIndex={-1}
                        className="flex aspect-square flex-col items-center justify-center gap-1.5 rounded-lg border border-dashed border-rail text-[13px] text-muted-foreground no-underline! transition-colors hover:border-input hover:bg-muted hover:text-foreground"
                    >
                        <Plus className="size-4" />
                        Your logo
                    </a>
                </li>
            ))}
        </ul>
    );
}

function PastSpots() {
    return (
        <ul aria-hidden className="grid grid-cols-6 gap-2">
            {Array.from({ length: 12 }, (_, index) => (
                <li
                    key={index}
                    className="aspect-square rounded-lg border border-dashed border-rail"
                />
            ))}
        </ul>
    );
}

export default function Index() {
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
                </section>

                <DocumentSection id="current" title="Current partners">
                    <p className="text-muted-foreground">
                        Nobody yet. These spots are open.
                    </p>
                    <OpenSpots />
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
                </DocumentSection>

                <DocumentSection id="past" title="Past partners">
                    <p className="text-muted-foreground">
                        When a sponsorship ends, the partner moves here. Nobody
                        yet.
                    </p>
                    <PastSpots />
                </DocumentSection>

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
                        A spot on this page. You're at the top while you
                        sponsor, and in the list of past partners after that.
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
