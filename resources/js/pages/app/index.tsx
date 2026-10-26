import { Head } from '@inertiajs/react';
import { Download } from 'lucide-react';
import { useSyncExternalStore } from 'react';
import { Button } from '@/components/ui/button';
import { WordmarkText } from '@/components/wordmark-text';
import AppLayout from '@/layouts/app-layout';

type Release = {
    version: string;
    build: number;
    revision: string;
    released_at: string;
    minimum_system_version: string;
    notarized: boolean;
    size: number;
    sha256: string;
    download_url: string;
    url: string;
};

/** macOS counts in decimal megabytes, so the size matches what Finder shows. */
function megabytes(bytes: number) {
    return `${(bytes / 1_000_000).toFixed(1)} MB`;
}

/**
 * iPads asking for desktop sites send a Mac's user agent, which the server takes at its word; touch gives them away.
 */
function useIsMac(isMacAgent: boolean) {
    const hasTouch = useSyncExternalStore(
        noChanges,
        () => navigator.maxTouchPoints > 1,
        () => false,
    );

    return isMacAgent && !hasTouch;
}

function noChanges() {
    return () => {};
}

export default function Index({
    release,
    isMac: isMacAgent,
}: {
    release: Release | null;
    isMac: boolean;
}) {
    const isMac = useIsMac(isMacAgent);

    return (
        <>
            <Head title="Apps" />

            <header className="sticky top-0 z-10 flex h-14 items-center border-b bg-card px-4 sm:px-6">
                <h1 className="font-semibold">Apps</h1>
            </header>

            <article className="text-[15px] leading-relaxed">
                <section
                    aria-labelledby="mac-title"
                    className="px-4 pt-8 pb-6 sm:px-6 sm:pt-10"
                >
                    <div className="flex items-center gap-4">
                        <picture className="size-16 shrink-0 sm:size-[72px]">
                            <img
                                src="/images/apps/mac-icon-light.webp"
                                alt=""
                                width={256}
                                height={256}
                                className="size-full dark:hidden"
                            />
                            <img
                                src="/images/apps/mac-icon-dark.webp"
                                alt=""
                                width={256}
                                height={256}
                                className="hidden size-full dark:block"
                            />
                        </picture>
                        <h2
                            id="mac-title"
                            className="text-[1.75rem] leading-tight font-semibold tracking-tight text-balance sm:text-3xl"
                        >
                            <WordmarkText>/dev/null for Mac</WordmarkText>
                        </h2>
                    </div>

                    <p className="mt-4 max-w-prose text-pretty">
                        Your remotes and orgs in a native Mac app. Push, pull,
                        and react with everyone on{' '}
                        <WordmarkText>/dev/null</WordmarkText> without keeping a
                        tab open.
                    </p>

                    {release === null ? (
                        <div className="mt-6 space-y-2">
                            <Button size="lg" disabled className="h-11">
                                <Download aria-hidden />
                                Download for Mac
                            </Button>
                            <p className="font-mono text-[13px] text-muted-foreground">
                                fatal: no release published yet
                            </p>
                        </div>
                    ) : (
                        <div className="mt-6 space-y-3">
                            <div className="flex flex-wrap items-center gap-x-4 gap-y-2">
                                <Button size="lg" className="h-11" asChild>
                                    <a href={release.download_url} download>
                                        <Download aria-hidden />
                                        Download for Mac
                                    </a>
                                </Button>
                                <p className="font-mono text-[13px] text-muted-foreground tabular-nums">
                                    v{release.version} ·{' '}
                                    {megabytes(release.size)} · macOS{' '}
                                    {release.minimum_system_version.replace(
                                        /\.0$/,
                                        '',
                                    )}
                                    +
                                </p>
                            </div>
                            {!isMac && (
                                <p className="text-sm text-pretty text-muted-foreground">
                                    It runs only on macOS: open this page on
                                    your Mac.
                                </p>
                            )}
                            {!release.notarized && (
                                <p className="text-sm text-pretty text-muted-foreground">
                                    The first time you open it, macOS blocks it:
                                    go to System Settings → Privacy &amp;
                                    Security and click Open Anyway.
                                </p>
                            )}
                        </div>
                    )}
                </section>

                <figure className="px-4 pb-8 sm:px-6">
                    <div className="motion-safe:animate-apps-shot-in">
                        <img
                            src="/images/apps/mac.webp"
                            alt="/dev/null for Mac with an org open: a voice note, an image, emoji reactions, and who has pulled it, beside the list of remotes."
                            width={1858}
                            height={1288}
                            fetchPriority="high"
                            className="h-auto w-full"
                        />
                    </div>
                </figure>
            </article>
        </>
    );
}

Index.layout = AppLayout;
